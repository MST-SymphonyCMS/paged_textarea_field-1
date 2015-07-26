<?php

Class extension_Paged_textarea_field extends Extension
{
    public function getSubscribedDelegates()
    {
        return array(
            array(
                'page' => '/backend/',
                'delegate' => 'InitaliseAdminPageHead',
                'callback' => 'addAssetFilesToHead'
            )
        );
    }

    /**
     * Install
     */
    public function install()
    {
        return Symphony::Database()->query("
            CREATE TABLE IF NOT EXISTS `tbl_fields_paged_textarea` (
                `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `field_id` INT(11) UNSIGNED NOT NULL,
                `formatter` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
                `size` INT(3) UNSIGNED NOT NULL,
                `output_page` VARCHAR(255) DEFAULT NULL,
                `invalid_page_action` VARCHAR(25) DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `field_id` (`field_id`)
            )  ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
        ");
    }

    /**
     * Uninstall
     */
    public function uninstall()
    {
        return Symphony::Database()->query("DROP TABLE IF EXISTS `tbl_fields_paged_textarea`");
    }

    public function addAssetFilesToHead($context)
    {
        $page_callback = Administration::instance()->getPageCallback();
        if ($page_callback['driver'] == 'publish') {
            Administration::instance()->Page->addStylesheetToHead(
                URL . '/extensions/paged_textarea_field/assets/paged_textarea_field.css'
            );
            Administration::instance()->Page->addScriptToHead(
                URL . '/extensions/paged_textarea_field/assets/jquery-ui.min.js'
            );
            Administration::instance()->Page->addScriptToHead(
                URL . '/extensions/paged_textarea_field/assets/paged_textarea_field.js'
            );
        }
    }
}
