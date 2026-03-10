<?php

namespace CDW\Tests\Unit;

use CDW\Tests\CDWTestCase;

require_once CDW_PLUGIN_DIR . 'tests/php/stubs/wp-stubs.php';
require_once CDW_PLUGIN_DIR . 'includes/services/ai/class-cdw-ai-encryption.php';

class AiEncryptionTest extends CDWTestCase {

    public function test_encrypt_then_decrypt_returns_original_plaintext(): void {
        $plaintext  = 'sk-test-api-key-12345';
        $ciphertext = \CDW_AI_Encryption::encrypt( $plaintext );
        $decrypted  = \CDW_AI_Encryption::decrypt( $ciphertext );

        $this->assertSame( $plaintext, $decrypted );
    }

    public function test_encrypt_returns_non_empty_string(): void {
        $result = \CDW_AI_Encryption::encrypt( 'my-secret-key' );

        $this->assertIsString( $result );
        $this->assertNotEmpty( $result );
    }

    public function test_encrypt_produces_different_ciphertexts_for_same_plaintext(): void {
        $ct1 = \CDW_AI_Encryption::encrypt( 'same-key' );
        $ct2 = \CDW_AI_Encryption::encrypt( 'same-key' );

        $this->assertNotSame( $ct1, $ct2 );
    }

    public function test_decrypt_returns_empty_string_for_empty_ciphertext(): void {
        $this->assertSame( '', \CDW_AI_Encryption::decrypt( '' ) );
    }

    public function test_decrypt_returns_empty_string_for_garbage_input(): void {
        $this->assertSame( '', \CDW_AI_Encryption::decrypt( 'not-valid-base64!!!' ) );
    }
}
