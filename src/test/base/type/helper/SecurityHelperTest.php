<?php
namespace PSFS\test\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\SecurityHelper;

class SecurityHelperTest extends TestCase
{
    /**
     * @param string $secretOK
     * @param string $secretKO
     * @param string $moduleOK
     * @param string $moduleKO
     * @dataProvider getBatteryTest
     */
    public function testToken($secretOK = null, $secretKO = null, $moduleOK = null, $moduleKO = null) {
        $secretOK = $secretOK ?: uniqid('ok', false);
        $secretKO = $secretKO ?: uniqid('fail', false);
        $moduleOK = $moduleOK ?: 'testOK';
        $moduleKO = $moduleKO ?: 'testKO';

        $token = SecurityHelper::generateToken($secretOK, $moduleOK);
        $this->assertNotNull($token, 'Something happens trying to generate a new token');
        $this->assertNotEmpty($token, 'The token is empty');



        $this->assertTrue(SecurityHelper::checkToken($token, $secretOK, $moduleOK), 'Verification OK failed');
        $this->assertFalse(SecurityHelper::checkToken($token, $secretOK, $moduleKO), 'Verification KO with different module failed');
        $this->assertFalse(SecurityHelper::checkToken($token, $secretKO, $moduleOK), 'Verification KO with different secret failed');
        $this->assertFalse(SecurityHelper::checkToken($token, $secretKO, $moduleKO), 'Verification KO with different secret and module failed');
    }

    public function getBatteryTest() {
        $batch = [];
        for($i = 0, $ct = rand(10, 100); $i < $ct; $i++) {
            $batch[] = [
                uniqid('ok', false),
                uniqid('fail', false),
                uniqid('testOK', false),
                uniqid('testKO', false),
            ];
        }
        return $batch;
    }
}
