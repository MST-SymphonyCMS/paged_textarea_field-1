<?php

/**
 * @package toolkit
 */

require_once FACE . '/interface.exportablefield.php';
require_once FACE . '/interface.importablefield.php';

/**
 * < Description >
 */
class fieldPaged_Textarea extends Field implements ExportableField, ImportableField
{
    public function __construct()
    {
        parent::__construct();
        $this->_name = __('Paged Textarea');
        $this->_required = true;

        // Set default
        $this->set('show_column', 'no');
        $this->set('required', 'no');
    }


    /*-------------------------------------------------------------------------
        Definition:
    -------------------------------------------------------------------------*/

    public function canFilter()
    {
        return false;
    }

    /*-------------------------------------------------------------------------
        Setup:
    -------------------------------------------------------------------------*/

    public function createTable()
    {
        return Symphony::Database()->query(
            "CREATE TABLE IF NOT EXISTS tbl_entries_data_{$this->get('id')} (
              `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
              `entry_id` INT(11) UNSIGNED NOT NULL,
              `value` MEDIUMTEXT,
              `value_formatted` MEDIUMTEXT,
              PRIMARY KEY (`id`),
              UNIQUE KEY `entry_id` (`entry_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;"
        ) ? Symphony::Database()->query(
            "CREATE TABLE IF NOT EXISTS tbl_entries_data_{$this->get('id')}_extra (
              `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
              `entry_id` INT(11) UNSIGNED NOT NULL,
              `value` MEDIUMTEXT,
              `value_formatted` MEDIUMTEXT,
              `page` TINYINT(2) UNSIGNED DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;"
        ) : false;
    }
// FULLTEXT KEY `value` (`value`)

    public function tearDown()
    {
        Symphony::Database()->query(
            "DROP TABLE IF EXISTS tbl_entries_data_{$this->get('id')}_extra"
        );
        return true;
    }

    public function entryDataCleanup($entry_id, $data = null)
    {
        $where = is_array($entry_id)
            ? " `entry_id` IN (" . implode(',', $entry_id) . ") "
            : " `entry_id` = '$entry_id' ";

        Symphony::Database()->delete("tbl_entries_data_{$this->get('id')}", $where);
        Symphony::Database()->delete("tbl_entries_data_{$this->get('id')}_extra", $where);

        return true;
    }

/*-------------------------------------------------------------------------
    Utilities:
-------------------------------------------------------------------------*/

    protected function __applyFormatting($data, $validate = false, &$errors = null)
    {
        $result = '';

        if ($this->get('formatter')) {
            $formatter = TextformatterManager::create($this->get('formatter'));
            $result = $formatter->run($data);
        }

        if ($validate === true) {
            include_once(TOOLKIT . '/class.xsltprocess.php');

            if (!General::validateXML($result, $errors, false, new XsltProcess)) {
                $result = html_entity_decode($result, ENT_QUOTES, 'UTF-8');
                $result = $this->__replaceAmpersands($result);

                if (!General::validateXML($result, $errors, false, new XsltProcess)) {
                    return false;
                }
            }
        }

        return $result;
    }

    private function __replaceAmpersands($value)
    {
        return preg_replace('/&(?!(#[0-9]+|#x[0-9a-f]+|amp|lt|gt);)/i', '&amp;', trim($value));
    }

    /*-------------------------------------------------------------------------
        Settings:
    -------------------------------------------------------------------------*/

    public function findDefaults(array &$settings)
    {
        if (!isset($settings['size'])) {
            $settings['size'] = 15;
        }
        if (!isset($settings['invalid_page_action'])) {
            $settings['invalid_page_action'] = 'no-page';
        }
    }

    public function displaySettingsPanel(XMLElement &$wrapper, $errors = null)
    {
        parent::displaySettingsPanel($wrapper, $errors);

        $div = new XMLElement('div', null, array('class' => 'two columns'));

        $label = Widget::Label(__('Number of default rows'));
        $label->setAttribute('class', 'column');
        $input = Widget::Input('fields['.$this->get('sortorder').'][size]', (string)$this->get('size'));
        $label->appendChild($input);
        $div->appendChild($label);
        $div->appendChild($this->buildFormatterSelect($this->get('formatter'), 'fields['.$this->get('sortorder').'][formatter]', __('Text Formatter')));
        $wrapper->appendChild($div);

        $div = new XMLElement('div', null, array('class' => 'two columns'));
        $label = Widget::Label(
            __('Page to output in data source'), // . '<i>' . __('Leave blank for all pages') . '</i>',
            Widget::Input(
                'fields[' . $this->get('sortorder') . '][output_page]',
                (string)$this->get('output_page')
            ),
            'column'
        );
        $div->appendChild($label);

        $action = $this->get('invalid_page_action');
        $div->appendChild(
            Widget::Label(
                __('When page number is invalid'),
                Widget::Select(
                    'fields[' . $this->get('sortorder') . '][invalid_page_action]',
                    array(
                        array('no-page', $action == 'no-page', __('No output')),
                        array('first-page', $action == 'first-page', __('Output first page')),
                        array('error-page', $action == 'error-page', __('Redirect to 404 error page'))
                    )
                ),
                'column'
            )
        );

        $wrapper->appendChild($div);

        // Requirements and table display
        $this->appendStatusFooter($wrapper);
    }

    public function commit()
    {
        if (!parent::commit()) {
            return false;
        }

        $id = $this->get('id');

        if ($id === false) {
            return false;
        }

        $fields = array();

        if ($this->get('formatter') != 'none') {
            $fields['formatter'] = $this->get('formatter');
        }

        $fields['size'] = $this->get('size');
        $fields['output_page'] = $this->get('output_page');
        $fields['invalid_page_action'] = $this->get('invalid_page_action');

        return FieldManager::saveSettings($id, $fields);
    }

    /*-------------------------------------------------------------------------
        Publish:
    -------------------------------------------------------------------------*/

    public function displayPublishPanel(
        XMLElement &$wrapper,
        $data = null,
        $flagWithError = null,
        $fieldnamePrefix = null,
        $fieldnamePostfix = null,
        $entry_id = null
    )
    {
        $label = new XMLElement('p', $this->get('label'), array('class' => 'label'));
        if ($this->get('required') != 'yes') {
            $label->appendChild(new XMLElement('i', __('Optional')));
        }
        $wrapper->appendChild($label);

        $border_box = new XMLElement('div', null, array('class' => 'border-box'));

        $ctrl_panel = new XMLElement('div', null, array('class' => 'control-panel'));
        $button_set = new XMLElement('div', null, array('class' => 'button-set'));
        $button_set->appendChild(
            new XMLElement('button', 'Add', array('type' => 'button', 'class' =>'action', 'data-action' => 'add', 'title' => 'Add page'))
        );
        $button_set->appendChild(
            new XMLElement('button', 'Add <', array('type' => 'button', 'class' => 'action add-before', 'data-action' => 'add-before', 'title' => 'Add page before current page'))
        );
        $button_set->appendChild(
            new XMLElement('button', 'Add >', array('type' => 'button', 'class' => 'action add-after', 'data-action' => 'add-after', 'title' => 'Add page after current page'))
        );
        $button_set->appendChild(new XMLElement('button', 'Remove', array('type' => 'button', 'class' => 'action remove', 'data-action' => 'remove', 'title' => 'Remove current page')));
        $ctrl_panel->appendChild($button_set);

        $ctrl_panel->appendChild(new XMLElement('div', null, array('class' => 'button-set pages')));
        $border_box->appendChild($ctrl_panel);

        // Field for first page
        $textarea = Widget::Textarea(
            'fields[' . $this->get('element_name') . '][]',
            (int)$this->get('size'),
            50,
            (strlen($data['value']) != 0 ? General::sanitize($data['value']) : null),
            array('class' => 'current')
        );
        if ($this->get('formatter') != 'none') {
            $textarea->setAttribute('class', 'current ' . $this->get('formatter'));
        }
        $border_box->appendChild($textarea);

        // Additional fields
        if ($entry_id) {
            $values = Symphony::Database()->fetchCol('value',
                "SELECT `value`
                FROM tbl_entries_data_{$this->get('id')}_extra
                WHERE `entry_id` = $entry_id ORDER BY `page`;"
            );

            foreach ($values as $value) {
                $textarea = Widget::Textarea(
                    'fields[' . $this->get('element_name') . '][]',
                    (int)$this->get('size'),
                    50,
                    (strlen($value) != 0 ? General::sanitize($value) : null)
                );
                if ($this->get('formatter') != 'none') {
                    $textarea->setAttribute('class', $this->get('formatter'));
                }
                $border_box->appendChild($textarea);
            }
        }

        if ($flagWithError != null) {
            $wrapper->appendChild(Widget::Error($div, $flagWithError));
        } else {
            $wrapper->appendChild($border_box);
        }
    }

    public function checkPostFieldData($data, &$message, $entry_id = null)
    {
        $message = null;
        if ($this->get('required') == 'yes' && strlen(trim($data)) == 0) {
            $message = __('‘%s’ is a required field.', array($this->get('label')));
            return self::__MISSING_FIELDS__;
        }

        if ($this->__applyFormatting($data, true, $errors) === false) {
            $message = __('‘%s’ contains invalid XML.', array($this->get('label'))) . ' ' . __('The following error was returned:') . ' <code>' . $errors[0]['message'] . '</code>';
            return self::__INVALID_FIELDS__;
        }

        return self::__OK__;
    }

    public function processRawFieldData($data, &$status, &$message = null, $simulate = false, $entry_id = null)
    {
        $status = self::__OK__;
        if (!is_array($data)) {
            $data = array($data);
        }

        $value = array_shift($data);
        $value_formatted = $this->__applyFormatting($value, true, $errors);
        if ($value_formatted === false) {
            $value_formatted = General::sanitize($this->__applyFormatting($value));
        }
        $result = array('value' => $value, 'value_formatted' => $value_formatted);

        if (!$simulate) {
            $table = "tbl_entries_data_{$this->get('id')}_extra";
            Symphony::Database()->delete($table, $where = "`entry_id` = $entry_id");

            $page = 2;
            foreach ($data as $value) {
                $value_formatted = $this->__applyFormatting($value, true, $errors);
                if ($value_formatted === false) {
                    $value_formatted = General::sanitize($this->__applyFormatting($value));
                }
                //$data_formatted[] = htmlentities($formatted_value);
                Symphony::Database()->insert(
                    array(
                        'entry_id' => $entry_id,
                        'page' => $page,
                        'value' => $value,
                        'value_formatted' => $value_formatted
                    ), $table
                );
                $page++;
            }
        }

        return $result;
    }

    /*-------------------------------------------------------------------------
        Output:
    -------------------------------------------------------------------------*/

    public function fetchIncludableElements()
    {
        if ($this->get('formatter')) {
            return array(
                $this->get('element_name') . ': formatted',
                $this->get('element_name') . ': unformatted'
            );
        }

        return array(
            $this->get('element_name')
        );
    }

    public function appendFormattedElement(XMLElement &$wrapper, $data, $encode = false, $mode = null, $entry_id = null)
    {
        $attributes = array();

        if (!is_null($mode)) {
            $attributes['mode'] = $mode;
        }

        $params = Symphony::Engine()->Page()->_param;
        $output_page = $this->_settings['output_page'];
        if ($output_page) {
            //
            // Output single page
            //
            $page_out = preg_replace_callback(
                '/{\$([^}]+)}/',
                function($match) use ($params)
                {
                    if (isset($params[$match[1]])) {
                        return $params[$match[1]];
                    } else {
                        return '';
                    }
                },
                $output_page
            );

            if ($page_out == '1') {
                $row = $data;
            } else {
                $invalid_page_action = $this->_settings['invalid_page_action'];

                if (is_numeric($page_out)) {
                    $row = Symphony::Database()->fetchRow(
                        0, "SELECT `value`, `value_formatted`
                        FROM tbl_entries_data_{$this->get('id')}_extra
                        WHERE (`entry_id` = $entry_id AND `page` = $page_out);"
                    );
                    if (empty($row)) $page_out = null;
                } else {
                    $page_out = null;
                }
            }

            if (!$page_out) {
                if ($invalid_page_action == 'first-page') {
                    $row = $data;
                    $page_out = 1;
                } elseif ($invalid_page_action == 'error-page') {
                    throw new FrontendPageNotFoundException();
                }
            }

            if ($page_out) {
                $attributes['page'] = $page_out;
                if ($mode == 'formatted') {
                    if ($this->get('formatter') && isset($row['value_formatted'])) {
                        $value = $row['value_formatted'];
                        $value = $encode ? General::sanitize($value) : $value;
                    }
                } elseif ($mode == null or $mode == 'unformatted') {
                    $value = sprintf('<![CDATA[%s]]>', str_replace(']]>', ']]]]><![CDATA[>', $row['value']));
                }
            } else {
                $value = null;
                $attributes['error'] = 'No content found';
            }

            $wrapper->appendChild(
                new XMLElement(
                    $this->get('element_name'), $value, $attributes
                )
            );
        } else {
            //
            // Output all pages
            //
            $rows = Symphony::Database()->fetch(
                "SELECT `value`, `value_formatted`
                FROM tbl_entries_data_{$this->get('id')}_extra
                WHERE `entry_id` = $entry_id ORDER BY `page`;"
            );
            array_unshift($rows, $data);

            $page_out = 1;
            foreach ($rows as $row) {
                $attributes['page'] = $page_out;
                if ($mode == 'formatted' && $this->get('formatter') && isset($row['value_formatted'])) {
                    $value = $encode ? General::sanitize($row['value_formatted']) : $row['value_formatted'];
                } else {
                    $value = sprintf('<![CDATA[%s]]>', str_replace(']]>', ']]]]><![CDATA[>', $row['value']));
                }
                $wrapper->appendChild(
                    new XMLElement(
                        $this->get('element_name'), $value, $attributes
                    )
                );
                $page_out++;
            }
        }
    }

    /*-------------------------------------------------------------------------
        Import:
    -------------------------------------------------------------------------*/

    public function getImportModes()
    {
        return array(
            'getValue' =>       ImportableField::STRING_VALUE,
            'getPostdata' =>    ImportableField::ARRAY_VALUE
        );
    }

    public function prepareImportValue($data, $mode, $entry_id = null)
    {
        $message = $status = null;
        $modes = (object)$this->getImportModes();

        if ($mode === $modes->getValue) {
            return $data;
        } elseif ($mode === $modes->getPostdata) {
            return $this->processRawFieldData($data, $status, $message, true, $entry_id);
        }

        return null;
    }

    /*-------------------------------------------------------------------------
        Export:
    -------------------------------------------------------------------------*/

    /**
     * Return a list of supported export modes for use with `prepareExportValue`.
     *
     * @return array
     */
    public function getExportModes()
    {
        return array(
            'getHandle' =>      ExportableField::HANDLE,
            'getFormatted' =>   ExportableField::FORMATTED,
            'getUnformatted' => ExportableField::UNFORMATTED,
            'getPostdata' =>    ExportableField::POSTDATA
        );
    }

    /**
     * Give the field some data and ask it to return a value using one of many
     * possible modes.
     *
     * @param mixed $data
     * @param integer $mode
     * @param integer $entry_id
     * @return string|null
     */
    public function prepareExportValue($data, $mode, $entry_id = null)
    {
        $modes = (object)$this->getExportModes();

        // Export handles:
        if ($mode === $modes->getHandle) {
            if (isset($data['handle'])) {
                return $data['handle'];
            } elseif (isset($data['value'])) {
                return Lang::createHandle($data['value']);
            }

        // Export unformatted:
        } elseif ($mode === $modes->getUnformatted || $mode === $modes->getPostdata) {
            return isset($data['value'])
                ? $data['value']
                : null;

        // Export formatted:
        } elseif ($mode === $modes->getFormatted) {
            if (isset($data['value_formatted'])) {
                return $data['value_formatted'];
            } elseif (isset($data['value'])) {
                return General::sanitize($data['value']);
                //return $data['value'];
            }
        }

        return null;
    }


    /*-------------------------------------------------------------------------
        Events:
    -------------------------------------------------------------------------*/

    public function getExampleFormMarkup()
    {
        $label = Widget::Label($this->get('label'));
        $label->appendChild(Widget::Textarea('fields['.$this->get('element_name').']', (int)$this->get('size'), 50));

        return $label;
    }
}
