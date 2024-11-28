<?php

namespace Usman\Reddit;

use Usman\Reddit\Exception\RedditException;
use Usman\Reddit\Http\RedditUrlGeneratorInterface;
use Usman\Reddit\Storage\DataStorageInterface;

/**
 * This interface is responsible for the authentication process with Reddit.
 *
 * @author Usman Malik <malikdevelopers81@gmail.com>
 */
interface AuthenticatorInterface
{
    /**
     * Tries to get a new access token from data storage or code. If it fails, it will return null.
     *
     * @param RedditUrlGeneratorInterface $urlGenerator
     *
     * @return AccessToken|null A valid user access token, or null if one could not be fetched.
     *
     * @throws RedditException
     */
    public function fetchNewAccessToken(RedditUrlGeneratorInterface $urlGenerator);

    /**
     * Generate a login url.
     *
     * @param RedditUrlGeneratorInterface $urlGenerator
     * @param array                         $options
     *
     * @return string
     */
    public function getLoginUrl(RedditUrlGeneratorInterface $urlGenerator, $options = []);

    /**
     * Clear the storage.
     *
     * @return $this
     */
    public function clearStorage();

    /**
     * @param DataStorageInterface $storage
     *
     * @return $this
     */
    public function setStorage(DataStorageInterface $storage);
}
