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
 * Custom rules model for form spam protection, accessed via tags
 * @author James
 */
class RecaptchaV3Rule extends DataObject implements PermissionProvider
{

    /**
     * @var string
     */
    const TAKE_ACTION_BLOCK = 'Block';

    /**
     * @var string
     */
    const TAKE_ACTION_CAUTION = 'Caution';

    /**
     * @var string
     */
    const TAKE_ACTION_ALLOW = 'Allow';

    /**
     * @var string
     */
    private static $singular_name = 'Captcha Rule';

    /**
     * @var string
     */
    private static $plural_name = 'Captcha Rules';

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
        'ActionToTake' => "Enum('Block,Caution,Allow','Block')",// action to take
        'AutoCreated' => 'Boolean'// whether this rule was auto created
    ];

    /**
     * @var array
     */
    private static $summary_fields = [
        'Tag' => 'Tag',
        'Enabled.Nice' => 'Enabled?',
        'Score' => 'Threshold score',
        'Action' => 'Action (for analytics)',
        'ActionToTake' => 'Action to take',
        'AutoCreated.Nice' => 'Auto-created?'
    ];

    /**
     * @var array
     */
    private static $defaults = [
        'Enabled' => 0, // not enabled by default
        'Action' => '',
        'ActionToTake' => 'Block',
        'AutoCreated' => 0
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
    public function checkTagExists(string $tag) : bool
    {
        $tags = RecaptchaV3Rule::get()->filter(['Tag' => $tag]);
        if ($this->exists()) {
            $tags = $tags->exclude(['ID' => $this->ID]);
        }
        return $tags->count() > 0;
    }

    /**
     * Get all enabled rules
     */
    public static function getEnabledRules() : DataList
    {
        return RecaptchaV3Rule::get()
                ->filter(['Enabled' => 1])
                ->sort('Tag ASC');
    }

    /**
     * Get an **enabled** rule based on a tag
     */
    public static function getRuleByTag(string $tag) : ?RecaptchaV3Rule
    {
        $rules = self::getEnabledRules();
        $rule = $rules->filter(['Tag' => $tag])->first();
        return $rule;
    }

    /**
     * Create a rule based on a tag.
     * Returns the rule, or the existing rule if the tag exists
     * @param string $tag the tag to assign to a Rule
     * @param bool $enabled whether the rule created will be enabled
     */
    public static function createFromTag(string $tag, bool $enabled = false) : self
    {
        $rule = RecaptchaV3Rule::get()->filter(['Tag' => $tag])->first();
        if (!empty($rule->ID)) {
            return $rule;
        } else {
            $rule = RecaptchaV3Rule::create([
                'Tag' => $tag,
                'AutoCreated' => 1,
                'Enabled' => ($enabled ? 1 : 0),
                'ActionToTake' => self::TAKE_ACTION_BLOCK,
                'Score' => RecaptchaV3SpamProtector::getDefaultThreshold()
            ]);
            $rule->write();
            return $rule;
        }
    }

    /**
     * Auto title, as tag value
     */
    public function getTitle() : ?string
    {
        return $this->Tag;
    }

    /**
     * Return enabled as yes/no
     */
    public function getEnabledNice() : string
    {
        return $this->Enabled == 1 ?
            _t("NSWDPC\SpamProtection.ENABLED_YES", "Yes") :
            _t("NSWDPC\SpamProtection.ENABLED_NO", "No");
    }

    /**
     * Detailed version of record, use in Dropdown map()
     */
    public function getTagDetailed() : string
    {
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
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // if there is no score yet, use the default
        if (is_null($this->Score) || $this->Score < 0 || $this->Score > 100) {
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
        if (!empty($systemTags)) {
            foreach ($systemTags as $systemTag) {
                $selectTagOptions[ $systemTag ] = $systemTag;
            }
            $selectTag = DropdownField::create(
                'SelectTag',
                _t(
                    "NSWDPC\SpamProtection.RECAPTCHAV3_SELECT_A_TAG",
                    "Select a tag, this will override any tag provided at 'Enter a tag'"
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
                "A unique action for this rule, used for monitoring and analytics"
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
                self::TAKE_ACTION_BLOCK => _t("NSWDPC\SpamProtection.RECAPTCHAV3_ACTION_TO_TAKE_BLOCK", 'Block - the action will fail'),
                self::TAKE_ACTION_CAUTION => _t("NSWDPC\SpamProtection.RECAPTCHAV3_ACTION_TO_TAKE_CAUTION", 'Caution - the action may be allowed, and logged if allowed'),
                self::TAKE_ACTION_ALLOW => _t("NSWDPC\SpamProtection.RECAPTCHAV3_ACTION_TO_TAKE_ALLOW", 'Allow - the action will be allowed but logged')
            ]
        );

        $autoCreatedField = CheckBoxField::create(
            'AutoCreated',
            _t(
                "NSWDPC\SpamProtection.RECAPTCHAV3_AUTOCREATED_TITLE",
                'Whether this rule was auto-created by the system'
            )
        )->performReadonlyTransformation();
        if ($this->AutoCreated) {
            $autoCreatedField->setDescription(
                _t(
                    "NSWDPC\SpamProtection.RECAPTCHAV3_IS_AUTOCREATED_DESCRIPTION",
                    "This field was automatically created by the system. Please review prior to enabling."
                )
            );
        }

        $fields->addFieldsToTab(
            'Root.Main',
            [
                $enabledField,
                $rangeField,
                $actionField,
                $actionToTakeField
            ]
        );

        $fields->insertBefore('Enabled', $tagField);
        $fields->insertBefore('Action', $autoCreatedField);

        return $fields;
    }

    /**
     * Event handler called before writing to the database.
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if ($this->SelectTag) {
            $this->Tag = $this->SelectTag;
        }

        if (!$this->Tag) {
            throw new ValidationException(
                _t(
                    "NSWDPC\SpamProtection.TAG_REQUIRED_FOR_RULE",
                    "This rule requires a tag"
                )
            );
        } elseif ($this->checkTagExists($this->Tag)) {
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
        if (is_null($this->Score) || $this->Score < 0 || $this->Score > 100) {
            $this->Score = RecaptchaV3SpamProtector::getDefaultThreshold();
        }

        // If no action specified, use the tag name
        if (!$this->Action) {
            $this->Action = $this->Tag;
        }

        // remove disallowed characters, store the action in lowercase
        $this->Action = strtolower(TokenResponse::formatAction($this->Action));

        if (!$this->ActionToTake) {
            $this->ActionToTake = self::TAKE_ACTION_BLOCK;
        }
    }

    /**
     * @inheritdoc
     */
    public function canView($member = null)
    {
        return Permission::checkMember($member, 'RECAPTCHAV3_RULE_VIEW');
    }

    /**
     * @inheritdoc
     */
    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'RECAPTCHAV3_RULE_CREATE');
    }

    /**
     * @inheritdoc
     */
    public function canEdit($member = null)
    {
        return Permission::checkMember($member, 'RECAPTCHAV3_RULE_EDIT');
    }

    /**
     * @inheritdoc
     */
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
                'name' => _t('NSWDPC\SpamProtection.PERMISSION_VIEW', 'View form spam rules'),
                'category' => 'reCAPTCHAv3',
            ],
            'RECAPTCHAV3_RULE_EDIT' => [
                'name' => _t('NSWDPC\SpamProtection.PERMISSION_EDIT', 'Edit form spam rules'),
                'category' => 'reCAPTCHAv3',
            ],
            'RECAPTCHAV3_RULE_CREATE' => [
                'name' => _t('NSWDPC\SpamProtection.PERMISSION_CREATE', 'Create form spam rules'),
                'category' => 'reCAPTCHAv3',
            ],
            'RECAPTCHAV3_RULE_DELETE' => [
                'name' => _t('NSWDPC\SpamProtection.PERMISSION_DELETE', 'Delete form spam rules'),
                'category' => 'reCAPTCHAv3',
            ]
        ];
    }
}
