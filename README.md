# Laravel Outbox

[![Latest Version on Packagist](https://img.shields.io/packagist/v/webrek/laravel-outbox.svg?style=flat-square)](https://packagist.org/packages/webrek/laravel-outbox)
[![Total Downloads](https://img.shields.io/packagist/dt/webrek/laravel-outbox.svg?style=flat-square)](https://packagist.org/packages/webrek/laravel-outbox)
[![Tests](https://img.shields.io/github/actions/workflow/status/webrek/laravel-outbox/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/webrek/laravel-outbox/actions/workflows/tests.yml)
[![PHP Version](https://img.shields.io/packagist/php-v/webrek/laravel-outbox.svg?style=flat-square)](https://php.net)
[![License](https://img.shields.io/packagist/l/webrek/laravel-outbox.svg?style=flat-square)](LICENSE)

Un *transactional outbox* para Laravel. Coloca un mensaje **dentro de la misma
transacción de base de datos** que tu escritura de negocio, y un *relay* lo
entrega después con reintentos y *backoff*. La escritura y el mensaje hacen
*commit* juntos —o aterrizan ambos o ninguno— de modo que nunca publicas un
evento de un cambio que se revirtió, ni pierdes un evento de un cambio que sí se
confirmó.

Esta es la mitad productora del *exactly-once*. Combínalo con
[webrek/laravel-idempotency](https://github.com/webrek/laravel-idempotency) en
el consumidor para obtener efectos *exactly-once* de extremo a extremo sobre una
infraestructura *at-least-once*.

## Por qué

Despachar un *job* en cola, disparar un *webhook* o publicar en un *broker*
*después* de guardar un modelo es un *dual write*: si el proceso muere entre el
*commit* y el despacho, el efecto secundario se pierde. Hacerlo *antes* del
*commit* es peor: el efecto se dispara incluso si la transacción se revierte. El
patrón *outbox* elimina esa brecha escribiendo la intención en la misma base de
datos, dentro de la misma transacción, y entregándola desde ahí.

```php
use Illuminate\Support\Facades\DB;
use Webrek\Outbox\Facades\Outbox;

DB::transaction(function () use ($request) {
    $order = Order::create($request->validated());

    // Hace commit atómicamente con la orden. Sin orden, no hay mensaje — y viceversa.
    Outbox::publish('order.placed', ['order_id' => $order->id]);
});
```

## Instalación

```bash
composer require webrek/laravel-outbox
```

Publica y ejecuta la migración:

```bash
php artisan vendor:publish --tag=outbox-migrations
php artisan migrate
```

Opcionalmente publica la configuración:

```bash
php artisan vendor:publish --tag=outbox-config
```

> La tabla del *outbox* debe vivir en la **misma conexión** que los datos junto a
> los que colocas los mensajes —la atomicidad solo abarca la transacción de una
> sola conexión. Configura `outbox.connection` en consecuencia (por defecto usa
> tu conexión predeterminada).

## Entrega de mensajes mediante el relay

Ejecuta el *relay* como un *worker* de larga duración (como `queue:work`):

```bash
php artisan outbox:work
```

Reclama los mensajes vencidos con un *row lock* —es seguro correr varios
*workers* en paralelo—, entrega cada uno a un **publisher** y lo marca como
publicado. Una entrega fallida se reintenta con *backoff* exponencial hasta
`max_attempts`, tras lo cual el mensaje se descarta. Un mensaje que quedó en
`processing` por un *worker* que se cayó se reclama una vez que pasa
`claim_timeout`.

Procesa un solo lote y termina (útil para tareas programadas o pruebas):

```bash
php artisan outbox:work --once
```

Recorta los mensajes ya entregados de forma programada:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('outbox:prune')->daily();   // conserva las últimas `prune.retention_hours`
```

## Entrega de mensajes al exterior

Cómo llega un mensaje al mundo exterior depende de un **publisher**. De fábrica
el paquete incluye `EventPublisher`, que convierte cada mensaje en un evento
`OutboxMessageReady` que puedes escuchar:

```php
use Webrek\Outbox\Events\OutboxMessageReady;

Event::listen(OutboxMessageReady::class, function (OutboxMessageReady $event) {
    $message = $event->message;

    Http::post('https://example.test/hooks', $message->payload)->throw();
});
```

La entrega es síncrona: si el *listener* lanza una excepción, el mensaje se
reprograma; si retorna, el mensaje se marca como publicado.

¿Prefieres una clase dedicada? Implementa el contrato y apunta la configuración
hacia ella:

```php
use Webrek\Outbox\Contracts\Publisher;
use Webrek\Outbox\Models\OutboxMessage;

class BrokerPublisher implements Publisher
{
    public function publish(OutboxMessage $message): void
    {
        // empuja a Kafka / RabbitMQ / SNS / un endpoint HTTP…
        // lanza una excepción para reintentar, retorna para confirmar.
    }
}
```

```php
// config/outbox.php
'publisher' => App\Outbox\BrokerPublisher::class,
```

## Observabilidad

El *relay* dispara eventos de ciclo de vida a los que puedes engancharte para
métricas y alertas:

| Evento | Cuándo |
| --- | --- |
| `OutboxMessagePublished` | Un mensaje se entregó correctamente. |
| `OutboxMessageFailed` | Un intento falló; el mensaje se reintentará. |
| `OutboxMessageDiscarded` | Se agotó el presupuesto de reintentos; se abandona el mensaje. |

Cada uno lleva el `OutboxMessage`; los eventos de falla también llevan el `Throwable`.

## Recuperación de mensajes descartados

Un mensaje que agota su presupuesto de reintentos se marca como `failed` y se
deja en la tabla para inspección —nunca se descarta en silencio. Una vez que
hayas corregido el sistema *downstream*, regresa los mensajes a `pending` para
que el *relay* los intente de nuevo con un presupuesto fresco:

```bash
php artisan outbox:retry --all          # todos los mensajes descartados
php artisan outbox:retry <id> <id> …    # mensajes específicos
```

Para distribuir los reintentos de un gran *backlog* y que no se disparen todos a
la vez, sube `retry.jitter` (0–1) antes de reprocesar.

## Inspección del outbox

Observa de un vistazo cuántos mensajes hay en cada estado —y qué tan rezagado
está el más antiguo en `pending`:

```bash
php artisan outbox:status
```

## Simularlo en pruebas

`Outbox::fake()` intercambia el *outbox* por un registrador en memoria, de modo
que las pruebas de tu aplicación pueden verificar qué se publicaría sin escribir
en la base de datos ni ejecutar el *relay*:

```php
use Webrek\Outbox\Facades\Outbox;

Outbox::fake();

$this->post('/orders', [...]);

Outbox::assertPublished('order.placed', fn ($message) => $message->payload['id'] === $order->id);
Outbox::assertPublishedTimes('order.placed', 1);
Outbox::assertNothingPublished();   // o verifica que nada se haya filtrado
```

## Configuración

```php
return [
    'connection' => env('OUTBOX_CONNECTION'),   // misma conexión que tus datos de negocio
    'table' => 'outbox_messages',
    'publisher' => Webrek\Outbox\Publishers\EventPublisher::class,
    'max_attempts' => 10,                        // intentos antes de descartar
    'batch_size' => 100,                         // mensajes reclamados por pasada del relay
    'claim_timeout' => 300,                       // segundos antes de reclamar un mensaje atorado
    'retry' => [
        'base_seconds' => 10,                     // delay = base * multiplier^(attempt - 1)
        'max_seconds' => 3600,
        'multiplier' => 2,
        'jitter' => 0.0,                          // 0–1: distribuye los reintentos para evitar un thundering herd
    ],
    'prune' => [
        'retention_hours' => 168,
    ],
];
```

## Requisitos

| Componente | Versión |
| --------- | ------- |
| PHP | 8.2+ |
| Laravel | 12.x / 13.x |
| Base de datos | Cualquiera con transacciones (PostgreSQL, MySQL/MariaDB, SQLite, SQL Server) |

## Pruebas

```bash
composer install
composer test
```

## Contribuir

Consulta [CONTRIBUTING.md](CONTRIBUTING.md).

## Seguridad

Revisa la [política de seguridad](SECURITY.md) antes de reportar una
vulnerabilidad.

## Licencia

Publicado bajo la [licencia MIT](LICENSE).
