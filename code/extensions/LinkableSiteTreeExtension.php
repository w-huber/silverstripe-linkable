<?php

/**
 * An extension to add site tree option to linkable field.
 *
 * @package silverstripe-linkable
 * @license BSD License http://www.silverstripe.org/bsd-license
 * @author  <mohamed.alsharaf@chrometoaster.com>
 **/
class LinkableSiteTreeExtension extends DataExtension
{
    /**
     * @var array
     */
    private static $db = array(
        'Anchor' => 'Varchar(255)',
        'AnchorType' => 'Int',
    );

    /**
     * @var array
     */
    private static $has_one = array(
        'SiteTree' => 'SiteTree',
        'AnchorElement' => 'BaseElement'
    );

    /**
     * A map of object types that can be linked to
     * Custom dataobjects can be added to this
     *
     * @var array
     **/
    private static $types = array(
        'SiteTree' => 'Page on this website',
    );

    /**
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName(['AnchorType','AnchorElementID']);
        $anchor_types = [
            0 => _t('Linkable.ANCHORTYPE_TEXT', 'Anchor/Querystring'),
            1 => _t('Linkable.ANCHORTYPE_ELEMENTS', 'Anchor/Element'),
        ];
        // elements
        $elementsSource = function($siteTreeID) {
            if ($siteTreeID) {
              $page = Page::get()->byId($siteTreeID);
              if ($page) {
                $arr = $page->ElementArea()->AllElements()->sort('Sort ASC')->map('ID', 'DropdownTitle');
                return $arr->unshift(0 , '');
              }
            }
            return [];
        };
        // Site tree field as a combination of tree drop down and anchor text field
        $siteTreeField = DisplayLogicWrapper::create(
            $siteTree = TreeDropdownField::create(
                'SiteTreeID',
                _t('Linkable.PAGE', 'Page'),
                'SiteTree'
            ),
            $anchorType = DropdownField::create('AnchorType', _t('Linkable.ANCHORTYPE', 'Anchor type'), $anchor_types),
            $anchorElement = DependentDropdownField::create('AnchorElementID', _t('Linkable.ANCHORELEMENT', 'Anchor/Element'), $elementsSource)->setDepends($siteTree),
            $anchor = TextField::create(
                'Anchor',
                _t('Linkable.ANCHOR', 'Anchor/Querystring')
            )->setRightTitle(_t('Linkable.ANCHORINFO', 'Include # at the start of your anchor name or, ? at the start of your querystring'))
        )->displayIf("Type")->isEqualTo("SiteTree")->end();

        $anchor->displayIf("AnchorType")->isEqualTo(0);
        $anchorElement->displayIf("AnchorType")->isEqualTo(1);

        // Insert site tree field after the file selection field
        $fields->insertAfter('Type', $siteTreeField);

        // Display warning if the selected page is deleted or unpublished
        if ($this->owner->SiteTreeID && !$this->owner->SiteTree()->isPublished()) {
            $fields
                ->dataFieldByName('SiteTreeID')
                ->setRightTitle(_t('Linkable.DELETEDWARNING', 'Warning: The selected page appears to have been deleted or unpublished. This link may not appear or may be broken in the frontend'));
        }
    }

    public function updateLinkURL(&$LinkURL) {
        if ($this->owner->Type == 'SiteTree' && $this->owner->AnchorType) {
            if ($this->owner->AnchorElementID) {
                $LinkURL = $this->owner->SiteTree()->Link('#'.$this->owner->AnchorElement()->getAnchor());
            } else {
                $LinkURL = $this->owner->SiteTree()->Link();
            }
        }
    }
}
