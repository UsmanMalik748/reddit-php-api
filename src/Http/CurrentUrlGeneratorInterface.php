<?php

namespace Usman\Reddit\Http;

/**
 * An interface to get the current URL.
 *
 * @author Usman Malik <malikdevelopers81@gmail.com>
 */
interface CurrentUrlGeneratorInterface
{
    /**
     * Returns the current URL.
     *
     * @return string The current URL
     */
    public function getCurrentUrl();

    /**
     * Should we trust forwarded headers?
     *
     * @param bool $trustForwarded
     *
     * @return $this
     */
    public function setTrustForwarded($trustForwarded);
}
