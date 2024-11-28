<?php

namespace Usman\Reddit\Storage;

use Usman\Reddit\Exception\InvalidArgumentException;

/**
 * @author Usman Malik <malikdevelopers81@gmail.com>
 */
abstract class BaseDataStorage implements DataStorageInterface
{
    public static $validKeys = ['state', 'code', 'access_token', 'redirect_uri'];

    /**
     * {@inheritdoc}
     */
    public function clearAll()
    {
        foreach (self::$validKeys as $key) {
            $this->clear($key);
        }
    }

    /**
     * Validate key. Throws an exception if key is not valid.
     *
     * @param string $key
     *
     * @throws InvalidArgumentException
     */
    protected function validateKey($key)
    {
        if (!in_array($key, self::$validKeys)) {
            throw new InvalidArgumentException('Unsupported key "%s" passed to Reddit data storage. Valid keys are: %s', $key, implode(', ', self::$validKeys));
        }
    }

    /**
     * Generate an ID to use with the data storage.
     *
     * @param $key
     *
     * @return string
     */
    protected function getStorageKeyId($key)
    {
        return 'reddit_'.$key;
    }
}
