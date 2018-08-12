<?php

namespace IntegratedExperts\BehatSteps\D7;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Symfony\Component\Filesystem\Filesystem;
use ZipArchive;

/**
 * Trait FileDownloadTrait.
 */
trait FileDownloadTrait {

  /**
   * Information about downloaded file.
   *
   * @var array
   */
  protected $fileDownloadDownloadedFileInfo;

  /**
   * @BeforeScenario
   */
  public function fileDownloadBeforeScenario(BeforeScenarioScope $scope) {
    if ($scope->getScenario()->hasTag('download')) {
      $this->fileDownloadRemoveTempDir();
      $this->fileDownloadPrepareTempDir();
    }
  }

  /**
   * @AfterScenario
   */
  public function fileDownloadAfterScenario(AfterScenarioScope $scope) {
    if ($scope->getScenario()->hasTag('download')) {
      $this->fileDownloadRemoveTempDir();
    }
  }

  /**
   * @Then I download file from :url
   */
  public function fileDownloadFrom($url) {
    if (empty(parse_url($url, PHP_URL_HOST))) {
      $url = rtrim($this->getMinkParameter('base_url'), '/') . '/' . ltrim($url, '/');
    }

    /** @var Behat\Mink\Driver\BrowserKitDriver $driver */
    $driver = $this->getSession()->getDriver();
    $cookies = $driver->getClient()->getCookieJar()->allValues($driver->getCurrentUrl());
    $cookie_list = [];
    foreach ($cookies as $cookie_name => $cookie_value) {
      $cookie_list[] = $cookie_name . '=' . $cookie_value;
    }

    $this->fileDownloadDownloadedFileInfo = $this->fileDownloadProcess($url, [
      CURLOPT_COOKIE => implode('; ', $cookie_list),
    ]);

    if (!$this->fileDownloadDownloadedFileInfo['file_path']) {
      throw new \RuntimeException('Unable to download file from URL ' . $url);
    }
    $file_data = file_get_contents($this->fileDownloadDownloadedFileInfo['file_path']);
    if ($file_data === FALSE) {
      throw new \RuntimeException('Unable to load content for downloaded file from temporary local file');
    }

    $this->fileDownloadDownloadedFileInfo['content'] = $file_data;
  }

  /**
   * @Then I download file from link :link
   */
  public function fileDownloadFromLink($link) {
    $link_element = $this->fileDownloadAssertLinkPresence($link, TRUE);

    $url = $link_element->getAttribute('href');
    $this->fileDownloadFrom($url);
  }

  /**
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
   * @Then downloaded file contains:
   */
  public function fileDownloadAssertFileContains(PyStringNode $string) {
    $string = strval($string);
    if (!$this->fileDownloadDownloadedFileInfo) {
      throw new \RuntimeException('Downloaded file content has no data.');
    }
    $lines = preg_split('/\R/', $this->fileDownloadDownloadedFileInfo['content']);
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

    throw new \Exception('Unable to find a content line with searched string.');
  }

  /**
   * @Then downloaded file name is :name
   */
  public function fileDownloadAssertFileName($name) {
    if (!$this->fileDownloadDownloadedFileInfo || empty($this->fileDownloadDownloadedFileInfo['file_name'])) {
      throw new \RuntimeException('Downloaded file name content has no data.');
    }

    if ($name != $this->fileDownloadDownloadedFileInfo['file_name']) {
      throw new \Exception(sprintf('Downloaded file %s, but expected %s', $this->fileDownloadDownloadedFileInfo['file_name'], $name));
    }
  }

  /**
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
    if (!class_exists('ZipArchive')) {
      throw new \RuntimeException('ZIP extension is not enabled for PHP');
    }

    if (!$this->fileDownloadDownloadedFileInfo || empty($this->fileDownloadDownloadedFileInfo['file_path'])) {
      throw new \RuntimeException('Downloaded file path data is not available.');
    }

    if (!$this->fileDownloadDownloadedFileInfo || empty($this->fileDownloadDownloadedFileInfo['content_type'])) {
      throw new \Exception('Downloaded file information does not have content type data.');
    }

    if (!in_array($this->fileDownloadDownloadedFileInfo['content_type'], ['application/octet-stream', 'application/zip'])) {
      throw new \Exception('Downloaded file does not have correct headers set for ZIP.');
    }

    $zip = new ZipArchive();
    $result = $zip->open($this->fileDownloadDownloadedFileInfo['file_path']);
    if ($result !== TRUE) {
      if ($result == ZipArchive::ER_NOZIP) {
        throw new \Exception('Downloaded file is not valid ZIP file.');
      }
      else {
        throw new \Exception('Downloaded file cannot be read.');
      }
    }

    return $zip;
  }

  /**
   * Download file.
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
      throw new \RuntimeException('Invalid download URL provided: ' . $url);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, $options);

    $content = curl_exec($ch);
    curl_close($ch);

    if (!$content) {
      throw new \RuntimeException('Unable to save temp file from URL ' . $url);
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
      throw new \RuntimeException('Unable to write downloaded content into file ' . $file_path);
    }

    print $file_path;

    return ['file_name' => $file_name, 'file_path' => $file_path] + $headers;
  }

  /**
   * Helper to extract downloaded file information from the response headers.
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
   * Helper to prepare temporary directory for file downloads.
   */
  protected function fileDownloadPrepareTempDir() {
    $fs = new Filesystem();
    if (!$fs->exists($this->fileDownloadGetTempDir())) {
      $fs->mkdir($this->fileDownloadGetTempDir());
    }
  }

  /**
   * Helper to remove temporary directory for file downloads.
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
