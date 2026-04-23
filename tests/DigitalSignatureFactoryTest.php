<?php

/**
 * Digital Signature Factory - Test Suite
 * اختبارات نظام التوقيع الرقمي
 */

namespace DigitalSignatureFactory\Tests;

use PHPUnit\Framework\TestCase;
use DigitalSignatureFactory\Core\DigitalSignatureFactory;
use DigitalSignatureFactory\Core\Certificate;

class DigitalSignatureFactoryTest extends TestCase
{
    private DigitalSignatureFactory $factory;
    private string $testCertPath;
    private string $testPFXPath;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // تهيئة المصنع
        $this->factory = new DigitalSignatureFactory([
            'pkcs11' => [
                'enabled' => false, // تعطيل PKCS#11 للاختبار
                'module_path' => '/usr/lib/softhsm/libsofthsm2.so'
            ],
            'pkcs12' => [
                'search_paths' => [__DIR__ . '/fixtures/']
            ]
        ]);
        
        // إنشاء مسارات الاختبار
        $this->testCertPath = __DIR__ . '/fixtures/test_cert.pem';
        $this->testPFXPath = __DIR__ . '/fixtures/test_token.pfx';
        
        // إنشاء ملفات اختبار وهمية
        $this->createTestFixtures();
    }
    
    /**
     * اختبار اكتشاف Tokens
     */
    public function testDetectTokens(): void
    {
        $tokens = $this->factory->detectTokens();
        
        $this->assertIsArray($tokens);
        // قد يكون فارغاً إذا لم يكن هناك Tokens متصلة
        $this->assertGreaterThanOrEqual(0, count($tokens));
    }
    
    /**
     * اختبار قراءة شهادة PEM
     */
    public function testReadPEMCertificate(): void
    {
        if (!file_exists($this->testCertPath)) {
            $this->markTestSkipped('Test certificate not found');
        }
        
        $cert = $this->factory->readCertificate($this->testCertPath, 'pem');
        
        $this->assertInstanceOf(Certificate::class, $cert);
        $this->assertNotEmpty($cert->getSubjectDN());
        $this->assertNotEmpty($cert->getSerialNumber());
    }
    
    /**
     * اختبار معلومات الشهادة
     */
    public function testCertificateInfo(): void
    {
        if (!file_exists($this->testCertPath)) {
            $this->markTestSkipped('Test certificate not found');
        }
        
        $cert = $this->factory->readCertificate($this->testCertPath, 'pem');
        $info = $cert->toArray();
        
        $this->assertArrayHasKey('subject_dn', $info);
        $this->assertArrayHasKey('issuer_dn', $info);
        $this->assertArrayHasKey('serial_number', $info);
        $this->assertArrayHasKey('thumbprint', $info);
        $this->assertArrayHasKey('valid_from', $info);
        $this->assertArrayHasKey('valid_to', $info);
        $this->assertArrayHasKey('is_valid', $info);
    }
    
    /**
     * اختبار إنشاء شهادة ذاتية التوقيع
     */
    public function testCreateRootCA(): void
    {
        $ca = $this->factory->createRootCA([
            'commonName' => 'Test Root CA',
            'organizationName' => 'Digital Signature Factory Test',
            'countryName' => 'EG',
            'keySize' => 2048 // استخدام 2048 للسرعة في الاختبار
        ]);
        
        $this->assertInstanceOf(Certificate::class, $ca);
        $this->assertEquals('Test Root CA', $ca->getOrganizationName());
        $this->assertTrue($ca->isValid());
    }
    
    /**
     * اختبار إنشاء شهادة توقيع كود
     */
    public function testCreateCodeSigningCertificate(): void
    {
        // إنشاء CA أولاً
        $ca = $this->factory->createRootCA([
            'commonName' => 'Test CA',
            'keySize' => 2048
        ]);
        
        // إنشاء شهادة توقيع كود
        $codeSignCert = $this->factory->createCodeSigningCertificate($ca, 'TestApp.exe');
        
        $this->assertInstanceOf(Certificate::class, $codeSignCert);
        $eku = $codeSignCert->getExtendedKeyUsage();
        $this->assertContains('codeSigning', $eku);
    }
    
    /**
     * اختبار مسح أجهزة USB
     */
    public function testScanUSBDevices(): void
    {
        $devices = $this->factory->scanUSBDevices();
        
        $this->assertIsArray($devices);
        // قد يكون فارغاً إذا لم يكن هناك أجهزة متصلة
    }
    
    /**
     * اختبار تحويل Array إلى XML
     */
    public function testArrayToXML(): void
    {
        $data = [
            'invoice' => [
                'number' => 'INV-001',
                'date' => '2026-01-15',
                'total' => 1000.00
            ]
        ];
        
        $xml = $this->invokePrivateMethod($this->factory, 'arrayToXML', [$data]);
        
        $this->assertIsString($xml);
        $this->assertStringContainsString('<Document>', $xml);
        $this->assertStringContainsString('<invoice>', $xml);
    }
    
    /**
     * اختبار إنشاء XML الفاتورة
     */
    public function testCreateInvoiceXML(): void
    {
        $invoice = [
            'InvoiceNumber' => 'INV-2026-001',
            'IssueDate' => '2026-01-15',
            'SellerName' => 'Test Company',
            'TotalAmount' => 1000.00
        ];
        
        $xml = $this->invokePrivateMethod($this->factory, 'createInvoiceXML', [$invoice]);
        
        $this->assertIsString($xml);
        $this->assertStringContainsString('<Invoice>', $xml);
        $this->assertStringContainsString('<InvoiceNumber>INV-2026-001</InvoiceNumber>', $xml);
    }
    
    /**
     * اختبار التحقق من OCSP URLs
     */
    public function testOCSPUrlsExtraction(): void
    {
        if (!file_exists($this->testCertPath)) {
            $this->markTestSkipped('Test certificate not found');
        }
        
        $cert = $this->factory->readCertificate($this->testCertPath, 'pem');
        $ocspUrls = $cert->getOCSPUrls();
        
        $this->assertIsArray($ocspUrls);
        // قد يكون فارغاً إذا لم تكن الشهادة تحتوي على OCSP URLs
    }
    
    /**
     * اختبار تحديد المزود
     */
    public function testProviderDetection(): void
    {
        if (!file_exists($this->testCertPath)) {
            $this->markTestSkipped('Test certificate not found');
        }
        
        $cert = $this->factory->readCertificate($this->testCertPath, 'pem');
        $provider = $cert->getProviderInfo();
        
        $this->assertIsString($provider);
        // سيكون Unknown للشهادات الاختبارية
    }
    
    /**
     * اختبار إعادة التصدير
     */
    public function testReExportCertificate(): void
    {
        // إنشاء شهادة اختبار
        $cert = $this->factory->createRootCA([
            'commonName' => 'Export Test CA',
            'keySize' => 2048
        ]);
        
        // إعادة التصدير بكلمة مرور جديدة
        $newPassphrase = 'new_test_password_123';
        $pkcs12 = $cert->exportToPKCS12($newPassphrase);
        
        $this->assertIsString($pkcs12);
        $this->assertStringContainsString('-----BEGIN PKCS12-----', $pkcs12);
    }
    
    /**
     * اختبار التحقق من الصلاحية
     */
    public function testCertificateValidity(): void
    {
        $cert = $this->factory->createRootCA([
            'commonName' => 'Validity Test CA',
            'keySize' => 2048
        ]);
        
        $this->assertTrue($cert->isValid());
        $this->assertNotEmpty($cert->getNotBefore());
        $this->assertNotEmpty($cert->getNotAfter());
    }
    
    /**
     * اختبار البصمة (Thumbprint)
     */
    public function testThumbprint(): void
    {
        $cert = $this->factory->createRootCA([
            'commonName' => 'Thumbprint Test CA',
            'keySize' => 2048
        ]);
        
        $thumbprint = $cert->getThumbprint();
        
        $this->assertIsString($thumbprint);
        $this->assertEquals(40, strlen($thumbprint)); // SHA1 = 40 hex chars
        $this->assertMatchesRegularExpression('/^[A-F0-9]+$/', $thumbprint);
    }
    
    /**
     * إنشاء ملفات الاختبار الوهمية
     */
    private function createTestFixtures(): void
    {
        $fixturesDir = __DIR__ . '/fixtures';
        
        if (!is_dir($fixturesDir)) {
            mkdir($fixturesDir, 0755, true);
        }
        
        // إنشاء شهادة اختبار PEM
        if (!file_exists($this->testCertPath)) {
            $dn = [
                'countryName' => 'EG',
                'organizationName' => 'Test Organization',
                'commonName' => 'Test Certificate'
            ];
            
            $privateKey = openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA
            ]);
            
            $csr = openssl_csr_new($dn, $privateKey, ['digest_alg' => 'sha256']);
            $cert = openssl_csr_sign($csr, null, $privateKey, 365, ['digest_alg' => 'sha256']);
            
            openssl_x509_export($cert, $output);
            file_put_contents($this->testCertPath, $output);
        }
    }
    
    /**
     * استدعاء دالة خاصة للاختبار
     */
    private function invokePrivateMethod(object $object, string $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        
        return $method->invokeArgs($object, $parameters);
    }
    
    protected function tearDown(): void
    {
        // تنظيف ملفات الاختبار
        if (file_exists($this->testCertPath)) {
            unlink($this->testCertPath);
        }
        
        parent::tearDown();
    }
}
