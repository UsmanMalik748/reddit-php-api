<?php

namespace Usman\Reddit\Exception;

class InvalidArgumentException extends RedditException
{
    /**
     * Treat this constructor as sprintf().
     */
    public function __construct()
    {
        parent::__construct(call_user_func_array('sprintf', func_get_args()));
    }
}
