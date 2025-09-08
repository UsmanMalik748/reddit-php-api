<?php

namespace Usman\Reddit;

/**
 * Class AccessToken
 *
 * Represents an OAuth access token and its associated data.
 *
 * @author Usman Malik
 */
class AccessToken
{
    /**
     * @var string|null The access token string.
     */
    private $accessToken;

    /**
     * @var string|null The refresh token string.
     */
    private $refreshToken;

    /**
     * @var string|null The token type (e.g., "bearer").
     */
    private $tokenType;

    /**
     * @var string|null The scope of the token.
     */
    private $scope;

    /**
     * @var array|[] The scope of the token.
     */
    private $response;

    /**
     * @var \DateTime|null Expiration date and time of the access token.
     */
    private $expiresAt;

    /**
     * Constructor
     *
     * Initializes the AccessToken using the response data.
     *
     * @param array $response Response containing token details.
     *                        Keys expected: access_token, scope, expires_in, refresh_token, token_type
     */
    public function __construct($response = [])
    {
        if (is_array($response) && !empty($response)) {
            $this->accessToken = $response['access_token'] ?? null;
            $this->refreshToken = $response['refresh_token'] ?? null;
            $this->tokenType = $response['token_type'] ?? null;
            $this->scope = $response['scope'] ?? null;
            $this->response = $response;
            if (isset($response['expires_in'])) {
                $this->expiresAt = (new \DateTime())->modify('+' . (int)$response['expires_in'] . ' seconds');
            }
        } elseif (is_string($response)) {
            $this->accessToken = $response;
        }
    }

     /**
     * Set the access token string.
     *
     * @param string|null $token
     *
     * @return $this
     */
    public function setAccessToken($token)
    {
        $this->token = $token;

        return $this;
    }

    /**
     * Get the access token string.
     *
     * @return string|null
     */
    public function getAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * Set the refresh token string.
     *
     * @param string|null $refreshToken
     *
     * @return $this
     */
    public function setRefreshToken($refreshToken)
    {
        $this->refreshToken = $refreshToken;

        return $this;
    }

    /**
     * Get the refresh token string.
     *
     * @return string|null
     */
    public function getRefreshToken()
    {
        return $this->refreshToken;
    }

    /**
     * Set the token type string.
     *
     * @param string|null $tokenType
     *
     * @return $this
     */
    public function setTokenType($tokenType)
    {
        $this->tokenType = $tokenType;

        return $this;
    }

    /**
     * Get the token type.
     *
     * @return string|null
     */
    public function getTokenType()
    {
        return $this->tokenType;
    }

    /**
     * Set the scope string.
     *
     * @param string|null $scope
     *
     * @return $this
     */
    public function setScope($scope)
    {
        $this->scope = $scope;

        return $this;
    }

    /**
     * Get the scope of the token.
     *
     * @return string|null
     */
    public function getScope()
    {
        return $this->scope;
    }

    /**
     * Set the expiration date and time.
     *
     * @param \DateTime|null $expiresAt
     *
     * @return $this
     */
    public function setExpiresAt(\DateTime $expiresAt = null)
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    /**
     * Get the expiration date and time.
     *
     * @return \DateTime|null
     */
    public function getExpiresAt()
    {
        return $this->expiresAt;
    }

    /**
     * Check if the token has expired.
     *
     * @return bool
     */
    public function isExpired()
    {
        return $this->expiresAt !== null && $this->expiresAt < new \DateTime();
    }

     /**
     * Does a token string exist?
     *
     * @return bool
     */
    public function hasToken()
    {
        return !empty($this->token);
    }

    /**
     * Update the access token and related details.
     *
     * @param array $response Response containing updated token details.
     *                        Keys expected: access_token, scope, expires_in, refresh_token, token_type
     *
     * @return $this
     */
    public function setResponse(array $response)
    {
        if (isset($response['access_token'])) {
            $this->setToken($response['access_token']);
        }

        if (isset($response['refresh_token'])) {
            $this->setRefreshToken($response['refresh_token']);
        }

        if (isset($response['token_type'])) {
            $this->setTokenType($response['token_type']);
        }

        if (isset($response['scope'])) {
            $this->setScope($response['scope']);
        }

        if (isset($response['expires_in'])) {
            $this->setExpiresAt(new \DateTime())->modify('+' . (int)$response['expires_in'] . ' seconds');
        }

        return $this;
    }


    /**
     * Get the reponse and related details.
     *
     * @param array $response Response containing updated token details.
     *                        Keys expected: access_token, scope, expires_in, refresh_token, token_type
     *
     * @return $this
     */
    public function getResponse()
    {
        return $this->response;
    }
}
