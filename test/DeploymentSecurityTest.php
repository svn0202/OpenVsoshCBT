<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class DeploymentSecurityTest extends TestCase
{
    public function testInstallDirectoryIsDeniedByApacheConfiguration(): void
    {
        $root = dirname(__DIR__);
        $this->assertStringContainsString('Require all denied', (string) file_get_contents($root . '/install/.htaccess'));
        $apache = (string) file_get_contents($root . '/docker/tcexam-apache.conf');
        $this->assertMatchesRegularExpression(
            '#<Directory "/var/www/html/install">.*?Require all denied.*?</Directory>#s',
            $apache,
        );
    }

    public function testSeededAdministratorDoesNotUseHistoricalPassword(): void
    {
        $sql = (string) file_get_contents(dirname(__DIR__) . '/install/db_data.sql');
        preg_match("/127\\.0\\.0\\.0', 'admin', '([^']+)'/", $sql, $matches);

        $this->assertArrayHasKey(1, $matches);
        $this->assertFalse(password_verify('1234', $matches[1]));
    }

    public function testInteractiveInstallerContainsNoInstallationLogic(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__) . '/install/install.php');

        $this->assertStringContainsString('http_response_code(404)', $source);
        $this->assertStringNotContainsString('F_create_database', $source);
        $this->assertStringNotContainsString('$_REQUEST', $source);
    }

    public function testLegacyUpdaterCannotDownloadOrExecuteCode(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__) . '/admin/code/tce_update.php');

        $this->assertStringNotContainsString('file_get_contents(K_UPDATE_SERVER', $source);
        $this->assertStringNotContainsString('exec(', $source);
        $this->assertStringContainsString('http_response_code(410)', $source);
    }
}
