<?php

namespace NSWDPC\SpamProtection;

use Silverstripe\Core\Config\Config;
use Silverstripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\CheckBoxField;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;

/**
 * Custom rules model for reCAPTCHAv3, accessed via tags
 * @author James
 */
class RecaptchaV3Rule extends DataObject implements PermissionProvider {

    const TAKE_ACTION_BLOCK = 'Block';

    const TAKE_ACTION_CAUTION = 'Caution';

    const TAKE_ACTION_ALLOW = 'Allow';

    /**
     * @var string
     */
    private static $singular_name = 'reCAPTCHAv3 Rule';

    /**
     * @var string
     */
    private static $plural_name = 'reCAPTCHAv3 Rules';

    /**
     * A list of system tags that can be used for common actions
     * @var array
     */
    private static $system_tags = [
        'lostpassword',
        'changepassword',
        'login',
        'register',
        'newslettersubscribe'
    ];

    /**
     * @var array
     */
    private static $db = [
        'Tag' => 'Varchar(64)',
        'Enabled' => 'Boolean',
        'Score' => 'Int',// 0-100
        'Action' => 'Varchar(255)',// custom action
        'ActionToTake' => "Enum('Block,Caution,Allow','Block')"// action to take
    ];

    /**
     * @var array
     */
    private static $summary_fields = [
        'Tag' => 'Tag',
        'Enabled.Nice' => 'Enabled?',
        'Score' => 'Threshold score',
        'Action' => 'Action (for analytics)',
        'ActionToTake' => 'Action to take'
    ];

    /**
     * @var array
     */
    private static $defaults = [
        'Enabled' => 0, // not enabled by default
        'Action' => '',
        'ActionToTake' => 'Block',
    ];

    /**
     * @var array
     */
    private static $indexes = [
        'Tag' => [
            'columns' => ['Tag'],
            'type' => 'unique'
        ],
        'Enabled' => true
    ];

    /**
     * Defines the database table name
     * @var string
     */
    private static $table_name = 'RecaptchaV3Rule';

    /**
     * Check if a Tag exists
     */
    public function checkTagExists(string $tag) : bool {
        $tags = RecaptchaV3Rule::get()->filter(['Tag' => $tag]);
        if($this->exists()) {
            $tags = $tags->exclude(['ID' => $this->ID]);
        }
        return $tags->count() > 0;
    }

    /**
     * Get all enabled rules
     */
    public static function getEnabledRules() : DataList {
        return RecaptchaV3Rule::get()
                ->filter(['Enabled' => 1])
                ->sort('Tag ASC');
    }

    /**
     * Get a rule based on a tag
     * @return RecaptchaV3Rule|null
     */
    public static function getRuleByTag(string $tag) {
        $tags = self::getEnabledRules();
        $tag = $tags->filter(['Tag' => $tag])->first();
        return $tag;
    }

    /**
     * Auto title, as tag value
     */
    public function getTitle() {
        return $this->Tag;
    }

    /**
     * Return enabled as yes/no
     */
    public function getEnabledNice() : string {
        return $this->Enabled == 1 ?
            _t("NSWDPC\SpamProtection.ENABLED_YES", "Yes") :
            _t("NSWDPC\SpamProtection.ENABLED_NO", "No");

    }

    /**
     * Detailed version of record, use in Dropdown map()
     */
    public function getTagDetailed() {
        return _t(
            "NSWDPC\SpamProtection.RECAPTCHAV3_TAG_DETAILED",
            "Tag: {tag}, Score: {score}, Enabled: {enabled}, Action: {action}, Action to take: {actionToTake}",
            [
                'tag' => $this->Tag,
                'score' => $this->Score,
                'enabled' => $this->getEnabledNice(),
                'action' => $this->Action,
                'actionToTake' => $this->ActionToTake
            ]
        );
    }

    /**
     * Define CMS fields
     */
    public function getCMSFields() {
        $fields = parent::getCMSFields();

        // if there is no score yet, use the default
        if(is_null($this->Score) || $this->Score < 0 || $this->Score > 100) {
            $this->Score = RecaptchaV3SpamProtector::getDefaultThreshold();
        }

        $fields->removeByName(['Score','Tag']);
        $rangeField = RecaptchaV3SpamProtector::getRangeCompositeField('Score', $this->Score);
        $tagField = CompositeField::create(
            TextField::create(
                'Tag',
                _t(
                    "NSWDPC\SpamProtection.RECAPTCHAV3_ENTER_A_TAG",
                    "Enter a tag"
                )
            )->setDescription(
                $this->exists() ? _t(
                    "NSWDPC\SpamProtection.RECAPTCHAV3_TAG_CHANGE",
                    "Changing this value will cause any actions using it to revert to the default site settings"
                ) : ""
            ),
        )->setTitle(
            _t(
                "NSWDPC\SpamProtection.RECAPTCHAV3_TAG",
                "Tag to identify this rule"
            )
        );

        // Provide an option to select a tag
        $systemTags = $this->config()->get('system_tags');
        if(!empty($systemTags)) {
            foreach($systemTags as $systemTag) {
                $selectTagOptions[ $systemTag ] = $systemTag;
            }
            $selectTag = DropdownField::create(
                'SelectTag',
                _t(
                    "NSWDPC\SpamProtection.RECAPTCHAV3_SELECT_A_TAG",
                    "Select a tag, this will override any tag entered"
                ),
                $selectTagOptions,
                $this->Tag
            )->setEmptyString('');
            $tagField->unshift($selectTag);
        }

        $enabledField = CheckBoxField::create(
            'Enabled',
            _t(
                "NSWDPC\SpamProtection.RECAPTCHAV3_ENABLED",
                "Enable this rule"
            )
        )->setDescription(
            _t(
                "NSWDPC\SpamProtection.RECAPTCHAV3_ENABLED_DESCRIPTION",
                "When left unchecked, the default settings for this website are used"
            )
        );
        $actionField = TextField::create(
            'Action',
            _t(
                "NSWDPC\SpamProtection.RECAPTCHAV3_ACTION",
                "Action, used for reCAPTCHAv3 score analytics"
            )
        )->setDescription(
            _t(
                "NSWDPC\SpamProtection.RECAPTCHAV3_ACTION_DESCRIPTION",
                "Only alphanumeric characters, slashes, and underscores are allowed. Actions must not be personally identifiable."
            )
        );
        $actionToTakeField = DropdownField::create(
            'ActionToTake',
            _t(
                "NSWDPC\SpamProtection.RECAPTCHAV3_ACTION_TO_TAKE",
                "Action to take when verification fails"
            ),
            [
                self::TAKE_ACTION_BLOCK => _t("NSWDPC\SpamProtection.RECAPTCHAV3_ACTION_TO_TAKE_BLOCK", 'Block'),
                self::TAKE_ACTION_CAUTION => _t("NSWDPC\SpamProtection.RECAPTCHAV3_ACTION_TO_TAKE_CAUTION", 'Caution'),
                self::TAKE_ACTION_ALLOW => _t("NSWDPC\SpamProtection.RECAPTCHAV3_ACTION_TO_TAKE_ALLOW", 'Allow')
            ]
        )->setDescription(
            _t(
                "NSWDPC\SpamProtection.RECAPTCHAV3_ACTION_TO_TAKE_DESCRIPTION",
                "For a form submission, choosing block will cause validation to fail. "
                . " The submitter will be presented with an error message."
                . " If 'Caution' is selected the system may take further action before allowing the action to take place"
            )
        );

        $fields->addFieldsToTab(
            'Root.Main', [
                $enabledField,
                $rangeField,
                $actionField,
                $actionToTakeField
            ]
        );

        $fields->insertBefore($tagField, 'Enabled');

        return $fields;

    }

    /**
     * Event handler called before writing to the database.
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if($this->SelectTag) {
            $this->Tag = $this->SelectTag;
        }

        if(!$this->Tag) {
            throw new ValidationException(
                _t(
                    "NSWDPC\SpamProtection.TAG_REQUIRED_FOR_RULE",
                    "This rule requires a tag"
                )
            );
        } else if($this->checkTagExists( $this->Tag )) {
            throw new ValidationException(
                _t(
                    "NSWDPC\SpamProtection.TAG_EXISTS_ERROR",
                    "The tag '{tag}' already exists. Please edit it.",
                    [
                        'tag' => $this->Tag
                    ]
                )
            );
        }

        // Use the default threshold score from config if the saved score is out of bounds
        if(is_null($this->Score) || $this->Score < 0 || $this->Score > 100) {
            $this->Score = RecaptchaV3SpamProtector::getDefaultThreshold();
        }

        // If not action specified, use the tag name
        if(!$this->Action) {
            $this->Action = $this->Tag;
        }

        // remove disallowed characters
        $this->Action = TokenResponse::formatAction($this->Action);

        if(!$this->ActionToTake) {
            $this->ActionToTake = self::TAKE_ACTION_BLOCK;
        }

    }

    public function canView($member = null)
    {
        return Permission::checkMember($member, 'RECAPTCHAV3_RULE_VIEW');
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'RECAPTCHAV3_RULE_CREATE');
    }

    public function canEdit($member = null)
    {
        return Permission::checkMember($member, 'RECAPTCHAV3_RULE_EDIT');
    }

    public function canDelete($member = null)
    {
        return Permission::checkMember($member, 'RECAPTCHAV3_RULE_DELETE');
    }

    /**
     * @return array
     */
    public function providePermissions()
    {
        return [
            'RECAPTCHAV3_RULE_VIEW' => [
                'name' => _t('NSWDPC\SpamProtection.PERMISSION_VIEW', 'View reCAPTCHAv3 rules'),
                'category' => 'reCAPTCHAv3',
            ],
            'RECAPTCHAV3_RULE_EDIT' => [
                'name' => _t('NSWDPC\SpamProtection.PERMISSION_EDIT', 'Edit reCAPTCHAv3 rules'),
                'category' => 'reCAPTCHAv3',
            ],
            'RECAPTCHAV3_RULE_CREATE' => [
                'name' => _t('NSWDPC\SpamProtection.PERMISSION_CREATE', 'Create reCAPTCHAv3 rules'),
                'category' => 'reCAPTCHAv3',
            ],
            'RECAPTCHAV3_RULE_DELETE' => [
                'name' => _t('NSWDPC\SpamProtection.PERMISSION_DELETE', 'Delete reCAPTCHAv3 rules'),
                'category' => 'reCAPTCHAv3',
            ]
        ];
    }

}