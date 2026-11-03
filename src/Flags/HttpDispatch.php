<?php

namespace Best\Blueprint\Flags;

/**
 * Knows when code runs inside an HTTP request handled by the app's kernel, including requests made
 * by an app's own tests ($this->get(...)), where runningInConsole() is still true.
 */
final class HttpDispatch
{
    public bool $active = false;
}
