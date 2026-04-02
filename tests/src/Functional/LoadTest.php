<?php

namespace Drupal\Tests\islandora_group\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Simple test to ensure that main page loads with module enabled.
 *
 * @group islandora_group
 */
#[RunTestsInSeparateProcesses]
class LoadTest extends BrowserTestBase {

  /**
   * Default theme to use during the test.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['islandora_group'];

  /**
   * {@inheritdoc}
   */
  // phpcs:ignore -- Do not disable strict config schema checking in tests.
  protected $strictConfigSchema = FALSE;

  /**
   * A user with permission to administer site configuration.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->user = $this->drupalCreateUser(['administer site configuration']);
    $this->drupalLogin($this->user);
  }

  /**
   * Tests that the home page loads with a 200 response.
   */
  public function testLoad() {
    $this->drupalGet(Url::fromRoute('<front>'));
    $this->assertSession()->statusCodeEquals(200);
  }

}
