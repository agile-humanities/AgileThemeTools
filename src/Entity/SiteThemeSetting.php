<?php

/**
 * @Entity
 *
 * Provides Doctrine annotations to define the site_theme_settings database table.
 * Modelled after the SiteSettings entity.
 *
 */
class SiteThemeSetting extends \Omeka\Entity\AbstractEntity
{
    /**
     * @Id
     * @Column(type="string", length=190)
     */
    protected $id;

    /**
     * @Id
     * @ManyToOne(targetEntity="Omeka\Entity\Site")
     * @JoinColumn(nullable=false, onDelete="CASCADE")
     */
    protected $site;

    /**
     * @Column(type="json_array")
     */
    protected $value;

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setSite(\Omeka\Entity\Site $site)
    {
        $this->site = $site;
    }

    public function getSite()
    {
        return $this->site;
    }

    public function setValue($value)
    {
        $this->value = $value;
    }

    public function getValue()
    {
        return $this->value;
    }
}
