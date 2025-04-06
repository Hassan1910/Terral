<?php
class JwtConfig {
    // JWT parameters
    private $key;
    private $issuer;
    private $audience;
    private $issuedAt;
    private $notBefore;
    private $expire;

    // Constructor
    public function __construct() {
        // Set credentials from environment variables if available
        if (class_exists('\\Dotenv\\Dotenv') && file_exists(dirname(dirname(__DIR__)) . '/.env')) {
            $dotenv = \Dotenv\Dotenv::createImmutable(dirname(dirname(__DIR__)));
            $dotenv->load();
            
            $this->key = isset($_ENV['JWT_SECRET']) ? $_ENV['JWT_SECRET'] : 'terral_api_secret_key';
            $this->issuer = isset($_ENV['JWT_ISSUER']) ? $_ENV['JWT_ISSUER'] : 'terral_api';
            $this->audience = isset($_ENV['JWT_AUDIENCE']) ? $_ENV['JWT_AUDIENCE'] : 'terral_app';
            $this->expire = isset($_ENV['JWT_EXPIRE']) ? (int)$_ENV['JWT_EXPIRE'] : 3600; // Default: 1 hour
        } else {
            // Default values
            $this->key = 'terral_api_secret_key'; // Change this in production for security
            $this->issuer = 'terral_api';
            $this->audience = 'terral_app';
            $this->expire = 3600; // 1 hour
        }
        
        // Set the time variables
        $this->issuedAt = time();
        $this->notBefore = $this->issuedAt; // Token valid immediately
        $this->expire = $this->issuedAt + $this->expire;
    }

    // Return JWT config array
    public function getConfig() {
        return [
            'key' => $this->key,
            'issuer' => $this->issuer,
            'audience' => $this->audience,
            'issuedAt' => $this->issuedAt,
            'notBefore' => $this->notBefore,
            'expire' => $this->expire
        ];
    }
}
?> 