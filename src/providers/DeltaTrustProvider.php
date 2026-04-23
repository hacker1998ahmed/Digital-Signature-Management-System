<?php

namespace DigitalSignatureFactory\Providers;

use DigitalSignatureFactory\Core\Certificate;

/**
 * DeltaTrustProvider - مزود خدمة دلتا تراست
 * 
 * يدعم PKCS#11 بشكل أساسي
 */
class DeltaTrustProvider
{
    private array $config;
    private ?Certificate $currentCert = null;
    
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }
    
    /**
     * الاتصال بـ Token DeltaTrust
     */
    public function connectToken(string $pin, string $tokenLabel = ''): Certificate
    {
        $modulePath = $this->config['pkcs11']['module_path'] ?? '/usr/lib/softhsm/libsofthsm2.so';
        
        $pkcs11Handle = new \DigitalSignatureFactory\Core\PKCS11Handle(
            $modulePath, 
            $pin, 
            $tokenLabel ?: 'DeltaTrust'
        );
        
        $this->currentCert = $pkcs11Handle->getCertificate();
        
        return $this->currentCert;
    }
    
    public function getProviderInfo(): array
    {
        return [
            'name' => 'DeltaTrust',
            'website' => 'https://www.deltatrust.com.eg',
            'supportedFormats' => ['pkcs11'],
            'tokenSupport' => true,
            'hsmSupport' => true
        ];
    }
}
