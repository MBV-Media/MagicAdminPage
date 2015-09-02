<?php
/**
 * @version 1.0.0
 * @author R4c00n <marcel.kempf93@gmail.com>
 * @licence MIT
 */

namespace MagicAdminPage;


/**
 * The class representing a WordPress admin page.
 *
 * @since 1.0.0
 * @author R4c00n <marcel.kempf93@gmail.com>
 * @licence MIT
 */
class MagicAdminPage {

    /**
     * @since 1.0.0
     * @var string
     */
    protected $settingsId;

    /**
     * @since 1.0.0
     * @var string
     */
    protected $pageTitle;

    /**
     * @since 1.0.0
     * @var string
     */
    protected $menuTitle;

    /**
     * @since 1.0.0
     * @var string
     */
    protected $capability;

    /**
     * @since 1.0.0
     * @var string
     */
    protected $menuSlug;

    /**
     * @since 1.0.0
     * @var string
     */
    protected $location;

    /**
     * @since 1.0.0
     * @var string
     */
    protected $iconUrl;

    /**
     * @since 1.0.0
     * @var null|number
     */
    protected $position;

    /**
     * @since 1.0.0
     * @var mixed[]
     */
    protected $fields = array();

    /**
     * @since 1.0.0
     * @param string $settingsId
     * @param string $pageTitle
     * @param string $menuTitle
     * @param null|number $position - (optional)
     * @param string $iconUrl - (optional)
     * @param string $capability
     * @param string $location - (optional)
     */
    public function __construct( $settingsId, $pageTitle, $menuTitle, $position = null,
                                 $iconUrl = '', $capability = 'manage_options',
                                 $location = 'menu' ) {
        $this->settingsId = $settingsId;
        $this->menuSlug = $settingsId;
        $this->pageTitle = $pageTitle;
        $this->menuTitle = $menuTitle;
        $this->position = $position;
        $this->iconUrl = $iconUrl;
        $this->capability = $capability;
        $this->location = $location;

        add_action( 'admin_init', array( $this, '_registerSettings' ) );
        add_action( 'admin_menu', array( $this, '_registerAdminPage' ) );
    }

    /**
     * Add a field to the fields array.
     *
     * @since 1.0.0
     * @param mixed[] $field
     * @return void
     */
    public function addField( $field ) {
        $this->fields[$field['name']] = $field;
    }

    /**
     * Add multiple fields to the fields array.
     *
     * @since 1.0.0
     * @param mixed[] $fields
     * @return void
     */
    public function addFields( $fields ) {
        foreach ( $fields as $key => $field ) {
            $field['name'] = $key;
            $this->addField( $field );
        }
    }

    /**
     * Register the settings and add the settings section.
     * Called by WordPress 'admin_init' action.
     *
     * @since 1.0.0
     * @return void
     */
    public function _registerSettings() {
        register_setting( $this->settingsId, $this->settingsId );
        add_settings_section(
            $this->settingsId,
            $this->menuTitle,
            array( $this, '_sectionConfig' ),
            $this->menuSlug
        );
    }

    /**
     * Add the settings fields.
     * Callback function for 'add_settings_section'.
     *
     * @since 1.0.0
     * @return void
     */
    public function _sectionConfig() {
        foreach ( $this->fields as $key => $options ) {
            $options['id'] = $key;
            add_settings_field(
                $key,
                $options['title'],
                array( $this, '_renderSettingsField' ),
                $this->menuSlug,
                $this->settingsId,
                $options
            );
        }
    }

    /**
     * Render a settings field.
     * Callback function for 'add_settings_field'.
     *
     * @since 1.0.0
     * @param mixed[] $args
     * @return void
     * @throws \Exception
     */
    public function _renderSettingsField( $args ) {
        $options = get_option( $this->settingsId );
        $type = isset( $args['type'] ) ? $args['type'] : 'text';
        $languages = array( get_locale() );
        $isMultilingual = isset( $args['multilang'] ) && $args['multilang'];
        $flagsDir = '';

        if ( !isset( $options[$args['id']] ) ) {
            $options[$args['id']] = array();
        }

        # Check if the field is multilingual
        if ( $isMultilingual ) {
            if ( !class_exists( 'SitePress' ) ) {
                throw new \Exception( 'To enable multilingual fields, the WPML plugin is required.' );
            }
            $languages = array();
            foreach ( $GLOBALS['sitepress']->get_active_languages() as $value ) {
                $languages[] = str_replace( '-', '_', $value['tag'] );
            }
            $flagsDir = WP_PLUGIN_DIR . '/sitepress-multilingual-cms/res/flags';
        }

        $fields = '<div class="magic-admin-page-field-wrapper">';

        # Language flag icons
        if ( $isMultilingual ) {
            $fields .= $this->getLanguageToggles( $languages, $flagsDir );
        }

        # Fields
        foreach ( $languages as $language ) {
            $field = '';
            if ( !isset( $options[$args['id']][$language] ) ) {
                $options[$args['id']][$language] = '';
                if ( $type === 'checkbox' ) {
                    $options[$args['id']][$language] = false;
                }
            }

            # Field attributes
            $class = sprintf( 'magic-admin-page-input %s', $type );
            $id = sprintf( '%s-%s', $args['id'], $language );

            # Field default value
            $default = isset( $args['default'] ) ? $args['default'] : '';
            if ( is_array( $default ) ) {
                $default = isset( $default[$language] ) ? $default[$language] : $default;
            }
            if ( !is_bool( $options[$args['id']][$language] )
                && empty( $options[$args['id']][$language] )
            ) {
                $options[$args['id']][$language] = $default;
            }

            # Field description
            if ( isset( $args['description'] ) ) {
                $field .= sprintf( '<p class="howto">%s</p>', $args['description'] );
            }

            $name = sprintf( '%s[%s][%s]', $this->settingsId, $args['id'], $language );
            $value = $options[$args['id']][$language];
            $field .= $this->getField( $language, $type, $name, $value, $class, $id, $args );
            $fields .= $field;
        }

        $fields .= '</div>';
        echo $fields;
    }

    /**
     * Generate language toggle buttons.
     *
     * @since 1.0.0
     * @param string[] $languages
     * @param string $flagsDir
     * @return string
     */
    protected function getLanguageToggles( $languages, $flagsDir ) {
        $languageToggles = '<div class="language-toggles">';
        foreach ( $languages as $language ) {
            $languageSplit = explode( '_', $language );
            $languageShort = array_shift( $languageSplit );
            $flagUrl = '';
            if ( file_exists( $flagsDir . '/' . $languageShort . '.png' ) ) {
                $flagUrl = str_replace( WP_PLUGIN_DIR, WP_PLUGIN_URL, $flagsDir ) . '/' . $languageShort . '.png';
            }
            $languageToggles .= sprintf(
                '<img src="%s" alt="%s" class="magic-admin-page-language-toggle" ' .
                'data-language="%s">&nbsp;',
                $flagUrl,
                $languageShort,
                $language
            );
        }
        $languageToggles .= '</div>';
        return $languageToggles;
    }

    /**
     * Generate a field.
     *
     * @since 1.0.0
     * @param string $language
     * @param string $type
     * @param string $name
     * @param string $value
     * @param string $class
     * @param string $id
     * @param mixed[] $args
     * @return string
     */
    protected function getField( $language, $type, $name, $value, $class, $id, $args ) {
        $hidden = $language === get_locale() ? '' : 'hidden';
        $field = sprintf(
            '<span class="magic-admin-page-field magic-admin-page-field-%s %s">',
            $language,
            $hidden
        );
        switch ( $type ) {
            case 'text':
            case 'hidden':
                $field .= $this->getInputField( $type, $name, $value, $class, $id );
                break;
            case 'textarea':
                $field .= $this->getTextArea( $name, $value, $class, $id );
                break;
            case 'select';
                $field .= $this->getSelect( $name, $value, $class, $id, $args );
                break;
            case 'checkbox':
                $field .= $this->getCheckBox( $name, $value, $class, $id );
                break;
        }
        $field .= '</span>';
        return $field;
    }

    /**
     * Generate an input field. The type can be specified with
     * the $type parameter.
     *
     * @since 1.0.0
     * @param string $type
     * @param string $name
     * @param string $value
     * @param string $class
     * @param string $id
     * @return string
     */
    protected function getInputField( $type, $name, $value, $class, $id ) {
        return sprintf(
            '<input type="%s" name="%s" value="%s" class="%s" id="%s" cols="50">',
            $type,
            $name,
            $value,
            $class,
            $id
        );
    }

    /**
     * Generate a textarea.
     *
     * @since 1.0.0
     * @param string $name
     * @param string $value
     * @param string $class
     * @param string $id
     * @return string
     */
    protected function getTextArea( $name, $value, $class, $id ) {
        return sprintf(
            '<textarea name="%s" class="%s" id="%s" cols="50" rows="10">%s</textarea>',
            $name,
            $class,
            $id,
            $value
        );
    }

    /**
     * Generate a select input.
     *
     * @since 1.0.0
     * @param string $name
     * @param string $value
     * @param string $class
     * @param string $id
     * @param mixed[] $args
     * @return string
     */
    protected function getSelect( $name, $value, $class, $id, $args ) {
        $isMultiple = isset( $args['multiple'] ) && $args['multiple'];
        $name = $isMultiple ? $name . '[]' : $name;
        $multiple = $isMultiple ? ' multiple="multiple"' : '';

        $height = '';
        if ( $isMultiple ) {
            if ( isset( $args['height'] ) ) {
                $height = sprintf( 'height:%s;', $args['height'] );
            } else {
                $height = 'height:85px;';
            }
        }

        if ( !isset( $args['options'] ) ) {
            throw new \InvalidArgumentException( 'Select fields must have an "options" property.' );
        }
        $options = '';
        foreach ( $args['options'] as $key => $option ) {
            # TODO: Check this with S.H!
            if ( is_numeric( $key ) && empty( $args['use_key'] ) ) {
                $key = $option;
            }
            $selected = '';
            if ( $key === $value
                || ( is_array( $value ) && in_array( $key, $value ) )
            ) {
                $selected = 'selected="selected"';
            }
            $options .= sprintf(
                '<option value="%s" %s>%s</option>',
                $key,
                $selected,
                $option
            );
        }
        return sprintf(
            '<select name="%s" class="%s" id="%s" %s style="%s">%s</select>',
            $name,
            $class,
            $id,
            $multiple,
            $height,
            $options
        );
    }

    /**
     * Generate a checkbox.
     *
     * @since 1.0.0
     * @param string $name
     * @param string $value
     * @param string $class
     * @param string $id
     * @return string
     */
    protected function getCheckBox( $name, $value, $class, $id ) {
        $checked = $value ? 'checked="checked"' : '';
        return sprintf(
            '<input type="checkbox" name="%s" class="%s" id="%s" %s>',
            $name,
            $class,
            $id,
            $checked
        );
    }

    /**
     * Register the admin page.
     * Called by WordPress 'admin_menu' action.
     *
     * @since 1.0.0
     * @return void
     */
    public function _registerAdminPage() {
        $addMenuFn = sprintf( 'add_%s_page', $this->location );
        if ( !function_exists( $addMenuFn ) ) {
            throw new \InvalidArgumentException(
                sprintf( 'Position "%s" is not valid.', $this->location )
            );
        }
        $adminPage = call_user_func_array( $addMenuFn, array(
            $this->pageTitle,
            $this->menuTitle,
            $this->capability,
            $this->menuSlug,
            array( $this, '_renderAdminPage' ),
            $this->iconUrl,
            $this->position,
        ) );
        add_action( 'load-' . $adminPage, array( $this, '_enqueueScripts' ) );
    }

    /**
     * Render the admin page.
     * Callback function for 'add_{$location}_page'.
     *
     * @since 1.0.0
     * @return void
     */
    public function _renderAdminPage() {
        if ( !current_user_can( $this->capability ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }
        ?>
        <div class="wrap">
            <?php settings_errors(); ?>
            <h2><?php echo $this->pageTitle; ?></h2>
            <br>

            <form method="POST" action="options.php">
                <?php settings_fields( $this->settingsId ); ?>
                <?php do_settings_sections( $this->menuSlug ); ?>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Enqueue scripts and styles.
     * Called by WordPress 'load-{$adminPage}' action.
     *
     * @since 1.0.0
     * @return void
     */
    public function _enqueueScripts() {
        add_action( 'admin_enqueue_scripts', function () {
            $subDir = str_replace( get_stylesheet_directory(), '', dirname( __FILE__ ) );
            $componentUrl = get_stylesheet_directory_uri() . $subDir;
            wp_enqueue_script( 'magic-admin-page-js', $componentUrl . '/js/magic-admin-page.js' );
            wp_enqueue_style( 'magic-admin-page-css', $componentUrl . '/css/magic-admin-page.css' );
        } );
    }

    /**
     * Get a set of options.
     *
     * @since 1.0.0
     * @param string $optionName
     * @param null|string $language
     * @return mixed[]
     */
    public static function getOption( $optionName, $language = null ) {
        $language = $language ? $language : get_locale();
        $result = array();
        $options = get_option( $optionName );
        if ( !empty( $options ) ) {
            foreach ( $options as $key => $value ) {
                if ( isset( $value[$language] ) ) {
                    $result[$key] = $value[$language];
                }
            }
        }
        return $result;
    }
}