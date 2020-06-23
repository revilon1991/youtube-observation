<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

/**
 * @ORM\Entity()
 * @ORM\Table()
 */
class Video
{
    use TimestampableEntity;

    /**
     * @ORM\Column(type="bigint")
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private string $id;

    /**
     * @ORM\Column(type="string")
     */
    private string $externalId;

    /**
     * @ORM\ManyToOne(targetEntity=Channel::class)
     * @ORM\JoinColumn()
     */
    private Channel $channel;

    /**
     * @ORM\Column(type="json")
     */
    private array $downloadLinks;

    /**
     * @ORM\Column(type="string")
     */
    private string $publicUrl;

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $id
     */
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getExternalId(): string
    {
        return $this->externalId;
    }

    /**
     * @param string $externalId
     */
    public function setExternalId(string $externalId): void
    {
        $this->externalId = $externalId;
    }

    /**
     * @return Channel
     */
    public function getChannel(): Channel
    {
        return $this->channel;
    }

    /**
     * @param Channel $channel
     */
    public function setChannel(Channel $channel): void
    {
        $this->channel = $channel;
    }

    /**
     * @return array
     */
    public function getDownloadLinks(): array
    {
        return $this->downloadLinks;
    }

    /**
     * @param array $downloadLinks
     */
    public function setDownloadLinks(array $downloadLinks): void
    {
        $this->downloadLinks = $downloadLinks;
    }

    /**
     * @return string
     */
    public function getPublicUrl(): string
    {
        return $this->publicUrl;
    }

    /**
     * @param string $publicUrl
     */
    public function setPublicUrl(string $publicUrl): void
    {
        $this->publicUrl = $publicUrl;
    }
}
