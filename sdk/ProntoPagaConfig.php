<?php

namespace ProntoPaga;

class ProntoPagaConfig
{
    public const API_URL_SANDBOX    = 'https://sandbox.prontopaga.com';
    
    public const API_URL_PRODUCTION = 'https://prontopaga.com';
    
    public const ALLOWED_IPS = [
        '81.33.166.195',
        '44.195.64.16', '54.236.195.158',            // Sandbox 1
        '51.79.102.8',             // Sandbox 2
        '54.207.141.85',           // Prod 1
        '44.219.63.240',           // Prod 2
        '52.206.25.128',           // Prod 3
    ];
    
    public const DEBUG = true;

    public const LOG_PATH = __DIR__ . '/../logs/prontopaga.log';
    
    public const SIGN_ALGORITHM = 'sha256';
    
    public const SECURE_REF_PARAM = 'psref';
    
    public const CLIENT_DOCUMENT_DEFAULT = '111111111';
}