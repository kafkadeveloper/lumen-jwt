<?php

namespace Yega\Auth;

use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use \Firebase\JWT\JWT;
use \Yega\Auth\JWTHelper;

class JWTGuard implements Guard
{
    use GuardHelpers;
    /**
     * The user we last attempted to retrieve.
     *
     * @var \Illuminate\Contracts\Auth\Authenticatable
     */
    protected $lastAttempted;

    /**
     * The request instance.
     *
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * The JWT Helper Object.
     *
     * @var \Yega\Auth\JWTHelper
     */
    protected $jwt;



    /**
     * Create a new authentication guard.
     *
     * @param  \Illuminate\Contracts\Auth\UserProvider  $provider
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public function __construct(JWTHelper $jwt, UserProvider $provider, Request $request)
    {
        $this->request = $request;
        $this->provider = $provider;
        $this->jwt = $jwt;
    }

    /**
     * Get the currently authenticated user.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function user()
    {
        // If we've already retrieved the user for the current request we can just
        // return it back immediately. We do not want to fetch the user data on
        // every call to this method because that would be tremendously slow.
        if (! is_null($this->user)) {
            return $this->user;
        }

        $user = null;

        if ($this->jwt->isHealthy()) {
            $user = $this->provider->retrieveById($this->jwt->getPayload()->user_id);
        }

        return $this->user = $user;
    }

    /**
     * Return the currently cached user.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set the current user.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @return $this
     */
    public function setUser(AuthenticatableContract $user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get the token for the current request.
     *
     * @return string
     */
    public function getTokenForRequest()
    {
        if (!$this->jwt->isHealthy() && $this->request->headers->has('Authorization')) {
          list($jwt_token) = sscanf( $this->request->headers->get('Authorization'), 'Bearer %s');
          $this->jwt->setToken($jwt_token);
        }

        return $this->jwt->getToken();
    }

    /**
     * Generate new token by ID.
     *
     * @param mixed $id
     *
     * @return string|null
     */
    public function generateTokenFromUser()
    {
      $payload =  [
            "context" => "market",
            "user_id" => $this->user->id,
            "email" => $this->user->email,
            "name" => $this->user->getFullName()
        ];

      return $this->jwt->newToken($this->user, $payload);
    }

    

    /**
     * Attempt to authenticate the user using the given credentials and return the token.
     *
     * @param array $credentials
     * @param bool  $login
     *
     * @return mixed
     */
    public function attempt(array $credentials = [])
    {
        $this->lastAttempted = $user = $this->provider->retrieveByCredentials($credentials);
        if ($this->hasValidCredentials($user, $credentials)) {
          $this->login($user);
          return true;
        }
        return false;
    }

    /**
     * Create a token for a user.
     *
     * @param JWTSubject $user
     *
     * @return string
     */
    public function login(AuthenticatableContract $user)
    {
        $this->setUser($user);
        return $this->generateTokenFromUser();
    }

    /**
     * Determine if the user matches the credentials.
     *
     * @param mixed $user
     * @param array $credentials
     *
     * @return bool
     */
    protected function hasValidCredentials(AuthenticatableContract $user, $credentials)
    {
        return !is_null($user) && $this->provider->validateCredentials($user, $credentials);
    }

    /**
     * Set the current request instance.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return $this
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;

        return $this;
    }
}
