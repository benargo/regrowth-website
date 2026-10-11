<?php

namespace App\Contracts\Http;

interface SharesBattleNetSession
{
    /**
     * The OAuth state sent to Battle.net, checked on the callback.
     */
    public const STATE_KEY = 'battlenet.oauth_state';

    /**
     * The user's Saloon `AccessTokenAuthenticator`, which carries its own expiry.
     */
    public const AUTHENTICATOR_KEY = 'battlenet.authenticator';

    /**
     * Where the callback sends the user. Only ever written by the server.
     */
    public const INTENDED_URL_KEY = 'battlenet.intended_url';
}
