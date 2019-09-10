<?php


namespace xeBook\Readium\Encrypt;

use xeBook\Readium\Encrypt\Exception\InvalidArgumentException;
use xeBook\Readium\Encrypt\Exception\EncryptException;
use xeBook\Readium\Encrypt\Exception\FilesystemException;

class Encrypt
{
    private const SUCCESS_CODE = 0;

    private const ERROR_CODES = [
        10 => 'Error creating json addedPublication.',
        20 => 'Error notifying the License Server.',
        30 => 'Error encrypting the publication.',
        40 => 'Error encrypting.',
        41 => 'Error opening output.',
        42 => 'Error opening packaged web publication.',
        43 => 'Error writing output file.',
        50 => 'Error building Web Publication package from PDF.',
        51 => 'Error reading the epub content.',
        60 => 'Error opening the epub file.',
        65 => 'Error on generate new contentID.',
        70 => 'Error opening input file.',
        80 => 'Error incorrect parameters for License Server.',

    ];
    /**
     * @var string
     */
    private $encryptTool;

    /**
     * @var string
     */
    private $licenseServerEndpoint;

    /**
     * @var string
     */
    private $licenseServerUsername;

    /**
     * @var string
     */
    private $licenseServerPassword;

    /**
     * Encrypt constructor.
     *
     * @param string      $encryptTool
     * @param string|null $licenseServerEndpoint
     * @param string|null $licenseServerUsername
     * @param string|null $licenseServerPassword
     */
    public function __construct(
        string $encryptTool,
        ?string $licenseServerEndpoint = null,
        ?string $licenseServerUsername = null,
        ?string $licenseServerPassword = null
    ) {
        if (!file_exists($encryptTool) || !is_readable($encryptTool) || !is_executable($encryptTool)) {
            throw new InvalidArgumentException(
                'The encrypt tool '.$encryptTool.' not exits or it is not readable or it is not executable.', 10
            );
        }

        if ($licenseServerEndpoint !== null && !filter_var($licenseServerEndpoint, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException(
                'Incorrect parameters the License Server endpoint '.$licenseServerEndpoint.' is not valid url.', 20
            );
        }

        if ($licenseServerEndpoint !== null && ($licenseServerUsername == null || $licenseServerPassword == null)) {
            throw new InvalidArgumentException(
                'Incorrect parameters,  License Server needs username and password,', 30
            );
        }

        $this->encryptTool           = $encryptTool;
        $this->licenseServerEndpoint = $licenseServerEndpoint;
        $this->licenseServerUsername = $licenseServerUsername;
        $this->licenseServerPassword = $licenseServerPassword;
    }


    /**
     * lcpencrypt protects an epub/pdf file for usage in an lcp environment
     *
     * @param string      $input               source epub/pdf file locator (file system or http GET)
     * @param string      $profile             encryption profile to use default basic.
     * @param bool        $sendToLicenseServer optional send to the License server
     * @param string|null $contentId           optional content identifier, if omitted a new one will be generated
     * @param string|null $output              optional target location for protected content (file system or http PUT)
     *                                         optional, file path of the target protected content.  If not set put file int tmp file system.
     * @return array
     */
    public function run(
        string $input,
        ?string $profile = 'basic',
        ?bool $sendToLicenseServer = true,
        ?string $contentId = null,
        ?string $output = null
    ) {

        if (!file_exists($input) || !is_readable($input)) {
            throw new FilesystemException('The input path '.$input.' is not readable.', 40);
        }

        if ($output === null) {
            $filename = basename($input);
            $output   = sys_get_temp_dir().DIRECTORY_SEPARATOR.$filename;
        }

        $command = sprintf('%s -input "%s" -profile "%s" ', $this->encryptTool, $input, $profile);

        if ($sendToLicenseServer == true) {
            $command .= sprintf(
                '-lcpsv "%s" -login "%s" -password "%s" ',
                $this->licenseServerEndpoint,
                $this->licenseServerUsername,
                $this->licenseServerPassword
            );
        }

        if ($contentId !== null) {
            $command .= sprintf('-contentid "%s" ', $contentId);
        }

        if ($output !== null) {
            $command .= sprintf('-output "%s" ', $output);
        }

        $outputExecCommand         = [];
        $returnExitCodeExecCommand = null;
        $returnExec                = exec($command, $outputExecCommand, $returnExitCodeExecCommand);

        if ($returnExitCodeExecCommand !== self::SUCCESS_CODE) {
            if (array_key_exists($returnExitCodeExecCommand, self::ERROR_CODES)) {
                throw new EncryptException(self::ERROR_CODES[$returnExitCodeExecCommand], $returnExitCodeExecCommand);
            }

            throw new EncryptException($returnExec, $returnExitCodeExecCommand);

        }

        // transform responseToJson
        $json = '';
        // clean first line with text License Server was notified
        if ($sendToLicenseServer == true) {
            unset($outputExecCommand[0]);
        }

        foreach ($outputExecCommand as $item) {
            $json .= $item;
        }

        return json_decode($json, true);
    }

}
