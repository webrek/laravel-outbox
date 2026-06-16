<?php

namespace Webrek\Outbox;

enum MessageStatus: string
{
    /** Staged, waiting to be relayed. */
    case Pending = 'pending';

    /** Claimed by a relay worker and being delivered. */
    case Processing = 'processing';

    /** Delivered successfully. */
    case Published = 'published';

    /** Gave up after exhausting the retry budget. */
    case Failed = 'failed';
}
