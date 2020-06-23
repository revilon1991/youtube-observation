<?php

namespace App\Service;

use App\Exception\CdnException;
use Exception;
use Generator;

use function dirname;
use function fclose;
use function fgets;
use function fread;
use function sprintf;
use function ssh2_auth_password;
use function ssh2_connect;
use function ssh2_exec;
use function ssh2_scp_recv;
use function ssh2_scp_send;
use function ssh2_sftp;
use function ssh2_sftp_mkdir;
use function ssh2_sftp_stat;
use function ssh2_sftp_symlink;
use function ssh2_sftp_unlink;
use function stream_set_blocking;
use function strlen;
use function substr;

class CdnService
{
    protected string $hostname;
    protected int $port;
    protected string $username;
    protected string $password;
    protected string $path;

    /**
     * @var Resource
     */
    protected $connection;

    /**
     * UploadService constructor.
     *
     * @param string $hostname
     * @param int $port
     * @param string $username
     * @param string $password
     * @param string $path
     */
    public function __construct($hostname, $port, $username, $password, $path)
    {
        $this->hostname = $hostname;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->path = $path;
    }

    /**
     * @param string $dirname
     */
    protected function createDirectory($dirname): void
    {
        $this->connect();

        $sftp = ssh2_sftp($this->connection);

        if ($this->isDirExist($dirname)) {
            return;
        }

        if (!ssh2_sftp_mkdir($sftp, $dirname, 0777, true)) {
            throw new CdnException(sprintf('Cannot create directory %s', $dirname));
        }
    }

    /**
     * @param string $relativeTarget
     * @param string $relativeLink
     *
     * @throws CdnException
     */
    public function createSymlink($relativeTarget, $relativeLink): void
    {
        $absoluteTarget = sprintf('%s/%s', $this->path, $relativeTarget);
        $absoluteLink = sprintf('%s/%s', $this->path, $relativeLink);

        $this->connect();

        $sftp = ssh2_sftp($this->connection);

        if (!ssh2_sftp_symlink($sftp, $absoluteTarget, $absoluteLink)) {
            throw new CdnException(sprintf('Cannot create symlink %s to %s.', $relativeTarget, $relativeLink));
        }
    }

    /**
     * @param string $relativeTarget
     *
     * @throws CdnException
     */
    public function deleteFile($relativeTarget): void
    {
        $absoluteTarget = sprintf('%s/%s', $this->path, $relativeTarget);

        $this->connect();

        $sftp = ssh2_sftp($this->connection);

        if (!ssh2_sftp_unlink($sftp, $absoluteTarget)) {
            throw new CdnException(sprintf('Cannot delete file %s.', $relativeTarget));
        }
    }

    /**
     * @param string $relativeTarget
     *
     * @return bool
     */
    public function isFileExist(string $relativeTarget): bool
    {
        $absoluteTarget = sprintf('%s/%s', $this->path, $relativeTarget);

        $this->connect();

        $sftp = ssh2_sftp($this->connection);

        try {
            $result = ssh2_sftp_stat($sftp, $absoluteTarget);
        } catch (Exception $e) {
            return false;
        }

        if ($result === false) {
            return false;
        }

        return true;
    }

    /**
     * @param string $absoluteSource
     * @param string $relativeTarget
     *
     * @throws CdnException
     */
    public function uploadFile($absoluteSource, $relativeTarget): void
    {
        $absoluteTarget = sprintf('%s/%s', $this->path, $relativeTarget);

        $this->createDirectory(dirname($absoluteTarget));

        $this->connect();

        if (!ssh2_scp_send($this->connection, $absoluteSource, $absoluteTarget, 0664)) {
            throw new CdnException(sprintf('Cannot copy %s to %s.', $absoluteSource, $absoluteTarget));
        }
    }

    /**
     * @param string $absoluteDestination
     * @param string $relativeTarget
     *
     * @throws CdnException
    */
    public function downloadFile(string $absoluteDestination, string $relativeTarget): void
    {
        $absoluteTarget = sprintf('%s/%s', $this->path, $relativeTarget);

        $this->connect();

        $result = @ssh2_scp_recv($this->connection, $absoluteTarget, $absoluteDestination);

        if (!$result) {
            throw new CdnException(sprintf('Cannot download file %s to %s', $absoluteTarget, $absoluteDestination));
        }
    }

    /**
     * @throws CdnException
     */
    protected function connect()
    {
        if ($this->connection) {
            return $this->connection;
        }

        $this->connection = ssh2_connect($this->hostname, $this->port);

        if (!$this->connection) {
            throw new CdnException(sprintf('SSH Connection failed to %s:%s', $this->hostname, $this->port));
        }

        if (!ssh2_auth_password($this->connection, $this->username, $this->password)) {
            throw new CdnException('The supplied username/password combination was not accepted.');
        }

        return $this->connection;
    }

    /**
     * @param string $dirname
     *
     * @return bool
     */
    private function isDirExist(string $dirname): bool
    {
        $this->connect();
        $cmd = sprintf('if test -d "%s"; then echo 1; fi', $dirname);
        $stream = ssh2_exec($this->connection, $cmd);
        stream_set_blocking($stream, true);

        return (bool)fread($stream, 4096);
    }

    /**
     * @param string $targetDir
     *
     * @return Generator
     *
     * @throws CdnException
     */
    public function getFileList(string $targetDir): Generator
    {
        $absolutePath = sprintf('%s/%s', $this->path, $targetDir);
        $this->connect();

        if (!$this->isDirExist($absolutePath)) {
            throw new CdnException(sprintf('Directory %s is not exists', $absolutePath));
        }

        $cmd = "find $absolutePath -type f";
        $rStream = ssh2_exec($this->connection, $cmd);

        if (!$rStream) {
            throw new CdnException(sprintf('Cannot execute command: %s', $cmd));
        }

        stream_set_blocking($rStream, true);

        while ($filePath = fgets($rStream)) {
            $slashLength = 1;
            $targetDirPosition = strlen($this->path) + $slashLength;
            $shortPath = substr($filePath, $targetDirPosition);

            yield $shortPath;
        }

        fclose($rStream);
    }

    /**
     * @param string $file
     *
     * @return void
     *
     * @throws CdnException
     */
    public function moveToBasket(string $file): void
    {
        $filePath = sprintf('%s/%s', $this->path, $file);
        $basketPath = sprintf('%s/%s/%s', $this->path, 'basket', $file);

        $this->connect();
        $this->createDirectory(dirname($basketPath));

        $cmd = 'mv ' . $filePath . ' ' . $basketPath;

        $rStream = ssh2_exec($this->connection, $cmd);

        if (!$rStream) {
            throw new CdnException(sprintf('Cannot move file %s to %s', $filePath, $basketPath));
        }

        stream_set_blocking($rStream, true);

        fclose($rStream);
    }
}
