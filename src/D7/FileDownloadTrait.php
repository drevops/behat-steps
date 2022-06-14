<?php

namespace DrevOps\BehatSteps\D7;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Driver\Selenium2Driver;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Trait FileDownloadTrait.
 *
 * To support unzipping of attachments, we are using ZipArchive class from PHP
 * zip extension.
 *
 * @see https://www.php.net/manual/en/zip.installation.php
 *
 * @package DrevOps\BehatSteps\D7
 */
trait FileDownloadTrait {

  /**
   * Information about downloaded file.
   *
   * @var array
   */
  protected $fileDownloadFileInfo;

  /**
   * Prepare scenario to work with this trait.
   *
   * @BeforeScenario
   */
  public function fileDownloadBeforeScenario(BeforeScenarioScope $scope) {
    // Allow to skip this by adding a tag.
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    if ($scope->getScenario()->hasTag('download')) {
      $this->fileDownloadRemoveTempDir();
      $this->fileDownloadPrepareTempDir();
    }
  }

  /**
   * Cleanup after scenario run.
   *
   * @AfterScenario
   */
  public function fileDownloadAfterScenario(AfterScenarioScope $scope) {
    // Allow to skip this by adding a tag.
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    if ($scope->getScenario()->hasTag('download')) {
      $this->fileDownloadRemoveTempDir();
    }
  }

  /**
   * Download a file from the url.
   *
   * @code
   * Then I download file from "/my/path"
   * Then I download file from "http://external.com/path"
   * Then I download file from "http://currentsite.com/path"
   * @endcode
   *
   * @Then I download file from :url
   */
  public function fileDownloadFrom($url) {
    if (empty(parse_url($url, PHP_URL_HOST))) {
      $url = rtrim($this->getMinkParameter('base_url'), '/') . '/' . ltrim($url, '/');
    }

    $cookie_list = [];

    /** @var Behat\Mink\Driver\BrowserKitDriver $driver */
    $driver = $this->getSession()->getDriver();
    if (method_exists($driver, 'getAllCookies')) {
      $cookies = $driver->getWebDriverSession()->getAllCookies();
      foreach ($cookies as $cookie) {
        $cookie_list[] = $cookie['name'] . '=' . $cookie['value'];
      }
    }
    else {
      $cookies = $driver->getClient()->getCookieJar()->allValues($driver->getCurrentUrl());
      foreach ($cookies as $cookie_name => $cookie_value) {
        $cookie_list[] = $cookie_name . '=' . $cookie_value;
      }
    }

    $this->fileDownloadFileInfo = $this->fileDownloadProcess($url, [
      CURLOPT_COOKIE => implode('; ', $cookie_list),
    ]);

    if (!$this->fileDownloadFileInfo['file_path']) {
      throw new \Exception(sprintf('Unable to download file from URL "%s"', $url));
    }

    $file_data = file_get_contents($this->fileDownloadFileInfo['file_path']);
    if ($file_data === FALSE) {
      throw new \Exception('Unable to load content for downloaded file from temporary local file');
    }

    $this->fileDownloadFileInfo['content'] = $file_data;
  }

  /**
   * Download the file from the specified link.
   *
   * Note tha this is a multistep action.
   *
   * @code
   * Then I download file from link "My link title"
   * @endcode
   *
   * @Then I download file from link :link
   */
  public function fileDownloadFromLink($link) {
    $link_element = $this->fileDownloadAssertLinkPresence($link, TRUE);

    $url = $link_element->getAttribute('href');
    $this->fileDownloadFrom($url);
  }

  /**
   * Assert the presence/absence of the download link.
   *
   * @code
   * Then I see download "/path/to/file.pdf" link "present: on the page
   * @endcode
   *
   * @Then I see download :link link :presence(on the page)
   */
  public function fileDownloadAssertLinkPresence($link, $presense) {
    $should_be_present = $presense == 'present';

    $page = $this->getSession()->getPage();
    $link_element = $page->findLink($link);

    if ($should_be_present && !$link_element) {
      throw new \Exception(sprintf('No link "%s" is present on the page, but expected to be present', $link));
    }
    elseif (!$should_be_present && $link_element) {
      throw new \Exception(sprintf('Link "%s" is present on the page, but expected to be absent', $link));
    }

    return $link_element;
  }

  /**
   * Assert the contents of the downloaded file.
   *
   * @code
   * Then downloaded file contains:
   * """
   * Test content within downloaded file.
   * """
   * @endcode
   *
   * @Then downloaded file contains:
   */
  public function fileDownloadAssertFileContains(PyStringNode $string) {
    $string = strval($string);

    if (!$this->fileDownloadFileInfo) {
      throw new \RuntimeException('Downloaded file content has no data.');
    }

    $lines = preg_split('/\R/', $this->fileDownloadFileInfo['content']);
    foreach ($lines as $line) {
      if (preg_match('/^\/.+\/[a-z]*$/i', $string)) {
        if (preg_match($string, $line)) {
          return;
        }
      }
      elseif (strpos($line, $string) !== FALSE) {
        return;
      }
    }

    throw new \Exception(sprintf('Unable to find a content line with searched string "%s"', $string));
  }

  /**
   * Assert the name of the downloaded file.
   *
   * @code
   * Then downloaded file name is "myfile.pdf"
   * @endcode
   *
   * @Then downloaded file name is :name
   */
  public function fileDownloadAssertFileName($name) {
    if (!$this->fileDownloadFileInfo || empty($this->fileDownloadFileInfo['file_name'])) {
      throw new \RuntimeException('Downloaded file name content has no data.');
    }

    if ($name != $this->fileDownloadFileInfo['file_name']) {
      throw new \Exception(sprintf('Downloaded file name is %s, but expected %s', $this->fileDownloadFileInfo['file_name'], $name));
    }
  }

  /**
   * Assert file presence of the files within downloaded ZIP archive.
   *
   * @code
   * Then downloaded file is zip archive that contains files:
   * | file1.txt     |
   * | dir/file2.txt |
   * @endcode
   *
   * @Then downloaded file is zip archive that contains files:
   */
  public function fileDownloadAssertZipContains(TableNode $files) {
    $zip = $this->fileDownloadOpenZip();

    $errors = [];
    foreach ($files->getColumn(0) as $line) {
      if ($zip->locateName($line) === FALSE) {
        $errors[] = sprintf('Unable to find file "%s" in archive', $line);
      }
    }

    if (!empty($errors)) {
      throw new \Exception(implode(PHP_EOL, $errors));
    }
  }

  /**
   * Assert file absence of the files within downloaded ZIP archive.
   *
   * @code
   * Then downloaded file is zip archive that does not contain files:
   * | file1.txt     |
   * | dir/file2.txt |
   * @endcode
   *
   * @Then downloaded file is zip archive that does not contain files:
   */
  public function fileDownloadAssertNoZipContains(TableNode $files) {
    $zip = $this->fileDownloadOpenZip();

    $errors = [];
    foreach ($files->getColumn(0) as $line) {
      if ($zip->locateName($line) !== FALSE) {
        $errors[] = sprintf('Found file "%s" in archive but should not', $line);
      }
    }

    if (!empty($errors)) {
      throw new \Exception(implode(PHP_EOL, $errors));
    }
  }

  /**
   * Open downloaded ZIP archive and validate contents.
   */
  protected function fileDownloadOpenZip() {
    if (!class_exists('\ZipArchive')) {
      throw new \RuntimeException('ZIP extension is not enabled for PHP');
    }

    if (!$this->fileDownloadFileInfo || empty($this->fileDownloadFileInfo['file_path'])) {
      throw new \RuntimeException('Downloaded file path data is not available.');
    }

    if (!$this->fileDownloadFileInfo || empty($this->fileDownloadFileInfo['content_type'])) {
      throw new \Exception('Downloaded file information does not have content type data.');
    }

    if (!in_array($this->fileDownloadFileInfo['content_type'], [
      'application/octet-stream', 'application/zip',
    ])) {
      throw new \Exception('Downloaded file does not have correct headers set for ZIP.');
    }

    $zip = new \ZipArchive();
    $result = $zip->open($this->fileDownloadFileInfo['file_path']);
    if ($result !== TRUE) {
      if ($result == \ZipArchive::ER_NOZIP) {
        throw new \Exception('Downloaded file is not a valid ZIP file.');
      }
      else {
        throw new \Exception('Downloaded file cannot be read.');
      }
    }

    return $zip;
  }

  /**
   * Download a file from URL.
   */
  protected function fileDownloadProcess($url, $options = []) {
    $response_headers = [];

    $options += [
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_HEADER => FALSE,
      CURLOPT_FOLLOWLOCATION => TRUE,
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_ENCODING => '',
      CURLOPT_USERAGENT => 'test',
      CURLOPT_AUTOREFERER => TRUE,
      CURLOPT_CONNECTTIMEOUT => 120,
      CURLOPT_TIMEOUT => 120,
      CURLOPT_HEADERFUNCTION => function ($ch, $header) use (&$response_headers) {
        $response_headers[] = $header;

        return strlen($header);
      },
    ];

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
      throw new \RuntimeException(sprintf('Invalid download URL provided "%s"', $url));
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, $options);

    $content = curl_exec($ch);
    curl_close($ch);

    if (!$content) {
      throw new \RuntimeException(sprintf('Unable to save temp file from URL "%s" ', $url));
    }

    // Extract meta information from headers.
    $headers = $this->fileDownloadParseHeaders($response_headers);

    // Resolve file path and name.
    $dir = $this->fileDownloadGetTempDir();
    // Try to extract name from the download string.
    $url_file_name = parse_url($url, PHP_URL_PATH);
    $url_file_name = $url_file_name ? basename($url_file_name) : $url_file_name;
    $headers['file_name'] = empty($headers['file_name']) && !empty($url_file_name) ? $url_file_name : $headers['file_name'];
    $file_path = !empty($headers['file_name']) ? $dir . DIRECTORY_SEPARATOR . $headers['file_name'] : tempnam($dir, 'behat');
    $file_name = basename($file_path);

    // Write file contents.
    $written = file_put_contents($file_path, $content);
    if ($written === FALSE) {
      throw new \RuntimeException(sprintf('Unable to write downloaded content into file "%s"', $file_path));
    }

    return ['file_name' => $file_name, 'file_path' => $file_path] + $headers;
  }

  /**
   * Extract downloaded file information from the response headers.
   *
   * @param array $headers
   *   Array of headers from CURL.
   *
   * @return array
   *   Array of parsed headers, if any.
   */
  protected function fileDownloadParseHeaders(array $headers) {
    $parsed_headers = [];

    foreach ($headers as $header) {
      if (preg_match('/Content-Disposition:\s*attachment;\s*filename\s*=\s*\"([^"]+)"/', $header, $matches) && isset($matches[1])) {
        $parsed_headers['file_name'] = trim($matches[1]);
        continue;
      }

      if (preg_match('/Content-Type:\s*(.+)/', $header, $matches) && isset($matches[1])) {
        $parsed_headers['content_type'] = trim($matches[1]);
        continue;
      }
    }

    return $parsed_headers;
  }

  /**
   * Prepare temporary directory for file downloads.
   */
  protected function fileDownloadPrepareTempDir() {
    $fs = new Filesystem();
    if (!$fs->exists($this->fileDownloadGetTempDir())) {
      $fs->mkdir($this->fileDownloadGetTempDir());
    }
  }

  /**
   * Remove temporary directory for file downloads.
   */
  protected function fileDownloadRemoveTempDir() {
    $fs = new Filesystem();
    if (!$fs->exists($this->fileDownloadGetTempDir())) {
      $fs->remove($this->fileDownloadGetTempDir());
    }
  }

  /**
   * Get temp download dir.
   */
  protected function fileDownloadGetTempDir() {
    return '/tmp/behat_downloads';
  }

}
