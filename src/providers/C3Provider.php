<?php

namespace DigitalSignatureFactory\Providers;

use DigitalSignatureFactory\Core\Certificate;

/**
 * C3Provider - مزود خدمة سيجي (C3)
 * 
 * يدعم HSM و SmartCard
 */
class C3Provider
{
    private array $config;
    private ?Certificate $currentCert = null;
    
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }
    
    /**
     * الاتصال بـ HSM
     */
    public function connectHSM(string $pin, string $hsmAddress = ''): Certificate
    {
        // اتصال مخصص لـ HSM
        $modulePath = $this->config['pkcs11']['module_path'] ?? '/usr/lib/softhsm/libsofthsm2.so';
        
        $pkcs11Handle = new \DigitalSignatureFactory\Core\PKCS11Handle($modulePath, $pin, 'C3-HSM');
        
        $this->currentCert = $pkcs11Handle->getCertificate();
        
        return $this->currentCert;
    }
    
    public function getProviderInfo(): array
    {
        return [
            'name' => 'C3',
            'website' => 'https://www.c3.com.eg',
            'supportedFormats' => ['pkcs11', 'hsm'],
            'tokenSupport' => true,
            'hsmSupport' => true
        ];
    }
}
