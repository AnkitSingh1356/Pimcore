<?php

/**
 * Inheritance: no
 * Variants: no
 *
 * Fields Summary:
 * - firstName [input]
 * - lastName [input]
 * - email [input]
 * - phone [input]
 * - dateOfBirth [date]
 * - gender [select]
 * - address [textarea]
 * - status [select]
 */

return \Pimcore\Model\DataObject\ClassDefinition::__set_state(array(
   'dao' => NULL,
   'id' => '1',
   'name' => 'Customer',
   'title' => '',
   'description' => '',
   'creationDate' => NULL,
   'modificationDate' => 1780338027,
   'userOwner' => 1,
   'userModification' => 1,
   'parentClass' => '',
   'implementsInterfaces' => '',
   'listingParentClass' => '',
   'useTraits' => '',
   'listingUseTraits' => '',
   'encryption' => false,
   'encryptedTables' => 
  array (
  ),
   'allowInherit' => false,
   'allowVariants' => false,
   'showVariants' => false,
   'layoutDefinitions' => 
  \Pimcore\Model\DataObject\ClassDefinition\Layout\Panel::__set_state(array(
     'name' => 'pimcore_root',
     'type' => NULL,
     'region' => NULL,
     'title' => NULL,
     'width' => 0,
     'height' => 0,
     'collapsible' => false,
     'collapsed' => false,
     'bodyStyle' => NULL,
     'datatype' => 'layout',
     'children' => 
    array (
      0 => 
      \Pimcore\Model\DataObject\ClassDefinition\Layout\Panel::__set_state(array(
         'name' => 'customerInformation',
         'type' => NULL,
         'region' => '',
         'title' => 'Customer Information',
         'width' => 0,
         'height' => 0,
         'collapsible' => false,
         'collapsed' => false,
         'bodyStyle' => NULL,
         'datatype' => 'layout',
         'children' => 
        array (
          0 => 
          \Pimcore\Model\DataObject\ClassDefinition\Data\Input::__set_state(array(
             'name' => 'firstName',
             'title' => 'First Name',
             'tooltip' => '',
             'mandatory' => true,
             'noteditable' => false,
             'index' => false,
             'locked' => false,
             'style' => NULL,
             'permissions' => NULL,
             'fieldtype' => '',
             'relationType' => false,
             'invisible' => false,
             'visibleGridView' => true,
             'visibleSearch' => true,
             'blockedVarsForExport' => 
            array (
            ),
             'defaultValue' => NULL,
             'columnLength' => 190,
             'regex' => '',
             'regexFlags' => 
            array (
            ),
             'unique' => false,
             'showCharCount' => false,
             'width' => NULL,
             'defaultValueGenerator' => '',
          )),
          1 => 
          \Pimcore\Model\DataObject\ClassDefinition\Data\Input::__set_state(array(
             'name' => 'lastName',
             'title' => 'Last Name',
             'tooltip' => '',
             'mandatory' => true,
             'noteditable' => false,
             'index' => false,
             'locked' => false,
             'style' => NULL,
             'permissions' => NULL,
             'fieldtype' => '',
             'relationType' => false,
             'invisible' => false,
             'visibleGridView' => true,
             'visibleSearch' => true,
             'blockedVarsForExport' => 
            array (
            ),
             'defaultValue' => NULL,
             'columnLength' => 190,
             'regex' => '',
             'regexFlags' => 
            array (
            ),
             'unique' => false,
             'showCharCount' => false,
             'width' => NULL,
             'defaultValueGenerator' => '',
          )),
          2 => 
          \Pimcore\Model\DataObject\ClassDefinition\Data\Input::__set_state(array(
             'name' => 'email',
             'title' => 'Email',
             'tooltip' => '',
             'mandatory' => true,
             'noteditable' => false,
             'index' => false,
             'locked' => false,
             'style' => NULL,
             'permissions' => NULL,
             'fieldtype' => '',
             'relationType' => false,
             'invisible' => false,
             'visibleGridView' => true,
             'visibleSearch' => true,
             'blockedVarsForExport' => 
            array (
            ),
             'defaultValue' => NULL,
             'columnLength' => 190,
             'regex' => '',
             'regexFlags' => 
            array (
            ),
             'unique' => false,
             'showCharCount' => false,
             'width' => NULL,
             'defaultValueGenerator' => '',
          )),
          3 => 
          \Pimcore\Model\DataObject\ClassDefinition\Data\Input::__set_state(array(
             'name' => 'phone',
             'title' => 'Phone Number',
             'tooltip' => '',
             'mandatory' => false,
             'noteditable' => false,
             'index' => false,
             'locked' => false,
             'style' => NULL,
             'permissions' => NULL,
             'fieldtype' => '',
             'relationType' => false,
             'invisible' => false,
             'visibleGridView' => true,
             'visibleSearch' => true,
             'blockedVarsForExport' => 
            array (
            ),
             'defaultValue' => NULL,
             'columnLength' => 190,
             'regex' => '',
             'regexFlags' => 
            array (
            ),
             'unique' => false,
             'showCharCount' => false,
             'width' => NULL,
             'defaultValueGenerator' => '',
          )),
          4 => 
          \Pimcore\Model\DataObject\ClassDefinition\Data\Date::__set_state(array(
             'name' => 'dateOfBirth',
             'title' => 'Date Of Birth',
             'tooltip' => '',
             'mandatory' => false,
             'noteditable' => false,
             'index' => false,
             'locked' => false,
             'style' => NULL,
             'permissions' => NULL,
             'fieldtype' => '',
             'relationType' => false,
             'invisible' => false,
             'visibleGridView' => true,
             'visibleSearch' => true,
             'blockedVarsForExport' => 
            array (
            ),
             'defaultValue' => NULL,
             'useCurrentDate' => false,
             'columnType' => 'date',
             'defaultValueGenerator' => '',
          )),
          5 => 
          \Pimcore\Model\DataObject\ClassDefinition\Data\Select::__set_state(array(
             'name' => 'gender',
             'title' => 'Gender',
             'tooltip' => '',
             'mandatory' => false,
             'noteditable' => false,
             'index' => false,
             'locked' => false,
             'style' => NULL,
             'permissions' => NULL,
             'fieldtype' => '',
             'relationType' => false,
             'invisible' => false,
             'visibleGridView' => true,
             'visibleSearch' => true,
             'blockedVarsForExport' => 
            array (
            ),
             'options' => 
            array (
              0 => 
              array (
                'key' => 'Male',
                'value' => 'male',
              ),
              1 => 
              array (
                'key' => 'Female',
                'value' => 'female',
              ),
              2 => 
              array (
                'key' => 'Other',
                'value' => 'other',
              ),
            ),
             'defaultValue' => NULL,
             'columnLength' => 190,
             'dynamicOptions' => false,
             'enforceValidation' => false,
             'defaultValueGenerator' => '',
             'width' => NULL,
             'optionsProviderType' => 'configure',
             'optionsProviderClass' => 'Male',
             'optionsProviderData' => 'male',
          )),
          6 => 
          \Pimcore\Model\DataObject\ClassDefinition\Data\Textarea::__set_state(array(
             'name' => 'address',
             'title' => 'Address',
             'tooltip' => '',
             'mandatory' => false,
             'noteditable' => false,
             'index' => false,
             'locked' => false,
             'style' => NULL,
             'permissions' => NULL,
             'fieldtype' => '',
             'relationType' => false,
             'invisible' => false,
             'visibleGridView' => true,
             'visibleSearch' => true,
             'blockedVarsForExport' => 
            array (
            ),
             'maxLength' => NULL,
             'showCharCount' => false,
             'excludeFromSearchIndex' => false,
             'height' => NULL,
             'width' => NULL,
          )),
          7 => 
          \Pimcore\Model\DataObject\ClassDefinition\Data\Select::__set_state(array(
             'name' => 'status',
             'title' => 'Customer Status',
             'tooltip' => '',
             'mandatory' => false,
             'noteditable' => false,
             'index' => false,
             'locked' => false,
             'style' => NULL,
             'permissions' => NULL,
             'fieldtype' => '',
             'relationType' => false,
             'invisible' => false,
             'visibleGridView' => true,
             'visibleSearch' => true,
             'blockedVarsForExport' => 
            array (
            ),
             'options' => 
            array (
              0 => 
              array (
                'key' => 'Active',
                'value' => 'active',
              ),
              1 => 
              array (
                'key' => 'Inactive',
                'value' => 'inactive',
              ),
            ),
             'defaultValue' => NULL,
             'columnLength' => 190,
             'dynamicOptions' => false,
             'enforceValidation' => false,
             'defaultValueGenerator' => '',
             'width' => NULL,
             'optionsProviderType' => 'configure',
             'optionsProviderClass' => NULL,
             'optionsProviderData' => NULL,
          )),
          8 =>
          \Pimcore\Model\DataObject\ClassDefinition\Data\Input::__set_state(array(
             'name' => 'city',
             'title' => 'City',
             'tooltip' => '',
             'mandatory' => false,
             'noteditable' => false,
             'index' => false,
             'locked' => false,
             'style' => NULL,
             'permissions' => NULL,
             'fieldtype' => '',
             'relationType' => false,
             'invisible' => false,
             'visibleGridView' => false,
             'visibleSearch' => true,
             'blockedVarsForExport' =>
            array (
            ),
             'defaultValue' => NULL,
             'columnLength' => 100,
             'regex' => '',
             'regexFlags' =>
            array (
            ),
             'unique' => false,
             'showCharCount' => false,
             'width' => NULL,
             'defaultValueGenerator' => '',
          )),
          9 =>
          \Pimcore\Model\DataObject\ClassDefinition\Data\Input::__set_state(array(
             'name' => 'country',
             'title' => 'Country',
             'tooltip' => '',
             'mandatory' => false,
             'noteditable' => false,
             'index' => false,
             'locked' => false,
             'style' => NULL,
             'permissions' => NULL,
             'fieldtype' => '',
             'relationType' => false,
             'invisible' => false,
             'visibleGridView' => false,
             'visibleSearch' => true,
             'blockedVarsForExport' =>
            array (
            ),
             'defaultValue' => NULL,
             'columnLength' => 100,
             'regex' => '',
             'regexFlags' =>
            array (
            ),
             'unique' => false,
             'showCharCount' => false,
             'width' => NULL,
             'defaultValueGenerator' => '',
          )),
          10 =>
          \Pimcore\Model\DataObject\ClassDefinition\Data\Select::__set_state(array(
             'name' => 'channel',
             'title' => 'Acquisition Channel',
             'tooltip' => '',
             'mandatory' => false,
             'noteditable' => false,
             'index' => false,
             'locked' => false,
             'style' => NULL,
             'permissions' => NULL,
             'fieldtype' => '',
             'relationType' => false,
             'invisible' => false,
             'visibleGridView' => false,
             'visibleSearch' => false,
             'blockedVarsForExport' =>
            array (
            ),
             'options' =>
            array (
              0 => array('key' => 'Web',       'value' => 'web'),
              1 => array('key' => 'Mobile',    'value' => 'mobile'),
              2 => array('key' => 'Retail',    'value' => 'retail'),
              3 => array('key' => 'Wholesale', 'value' => 'wholesale'),
              4 => array('key' => 'Partner',   'value' => 'partner'),
              5 => array('key' => 'Other',     'value' => 'other'),
            ),
             'defaultValue' => NULL,
             'columnLength' => 190,
             'dynamicOptions' => false,
             'enforceValidation' => false,
             'defaultValueGenerator' => '',
             'width' => NULL,
             'optionsProviderType' => '',
             'optionsProviderClass' => '',
             'optionsProviderData' => '',
          )),
          11 =>
          \Pimcore\Model\DataObject\ClassDefinition\Data\Select::__set_state(array(
             'name' => 'customerType',
             'title' => 'Customer Type',
             'tooltip' => '',
             'mandatory' => false,
             'noteditable' => false,
             'index' => false,
             'locked' => false,
             'style' => NULL,
             'permissions' => NULL,
             'fieldtype' => '',
             'relationType' => false,
             'invisible' => false,
             'visibleGridView' => false,
             'visibleSearch' => false,
             'blockedVarsForExport' =>
            array (
            ),
             'options' =>
            array (
              0 => array('key' => 'Individual', 'value' => 'individual'),
              1 => array('key' => 'Business',   'value' => 'business'),
              2 => array('key' => 'VIP',        'value' => 'vip'),
              3 => array('key' => 'Partner',    'value' => 'partner'),
            ),
             'defaultValue' => NULL,
             'columnLength' => 190,
             'dynamicOptions' => false,
             'enforceValidation' => false,
             'defaultValueGenerator' => '',
             'width' => NULL,
             'optionsProviderType' => '',
             'optionsProviderClass' => '',
             'optionsProviderData' => '',
          )),
          12 =>
          \Pimcore\Model\DataObject\ClassDefinition\Data\Select::__set_state(array(
             'name' => 'preferredSport',
             'title' => 'Preferred Sport',
             'tooltip' => '',
             'mandatory' => false,
             'noteditable' => false,
             'index' => false,
             'locked' => false,
             'style' => NULL,
             'permissions' => NULL,
             'fieldtype' => '',
             'relationType' => false,
             'invisible' => false,
             'visibleGridView' => false,
             'visibleSearch' => false,
             'blockedVarsForExport' =>
            array (
            ),
             'options' =>
            array (
              0  => array('key' => 'Football',    'value' => 'football'),
              1  => array('key' => 'Basketball',  'value' => 'basketball'),
              2  => array('key' => 'Tennis',      'value' => 'tennis'),
              3  => array('key' => 'Golf',        'value' => 'golf'),
              4  => array('key' => 'Cycling',     'value' => 'cycling'),
              5  => array('key' => 'Running',     'value' => 'running'),
              6  => array('key' => 'Swimming',    'value' => 'swimming'),
              7  => array('key' => 'Fitness',     'value' => 'fitness'),
              8  => array('key' => 'Cricket',     'value' => 'cricket'),
              9  => array('key' => 'Volleyball',  'value' => 'volleyball'),
              10 => array('key' => 'Badminton',   'value' => 'badminton'),
              11 => array('key' => 'Table Tennis','value' => 'table_tennis'),
              12 => array('key' => 'Boxing',      'value' => 'boxing'),
              13 => array('key' => 'Yoga',        'value' => 'yoga'),
              14 => array('key' => 'Other',       'value' => 'other'),
            ),
             'defaultValue' => NULL,
             'columnLength' => 190,
             'dynamicOptions' => false,
             'enforceValidation' => false,
             'defaultValueGenerator' => '',
             'width' => NULL,
             'optionsProviderType' => '',
             'optionsProviderClass' => '',
             'optionsProviderData' => '',
          )),
          13 =>
          \Pimcore\Model\DataObject\ClassDefinition\Data\Checkbox::__set_state(array(
             'name' => 'newsletterOptin',
             'title' => 'Newsletter Opt-In',
             'tooltip' => '',
             'mandatory' => false,
             'noteditable' => false,
             'index' => false,
             'locked' => false,
             'style' => NULL,
             'permissions' => NULL,
             'fieldtype' => '',
             'relationType' => false,
             'invisible' => false,
             'visibleGridView' => false,
             'visibleSearch' => false,
             'blockedVarsForExport' =>
            array (
            ),
             'defaultValue' => false,
             'defaultValueGenerator' => '',
          )),
        ),
         'locked' => false,
         'blockedVarsForExport' =>
        array (
        ),
         'fieldtype' => 'panel',
         'layout' => NULL,
         'border' => false,
         'icon' => NULL,
         'labelWidth' => 100,
         'labelAlign' => 'left',
      )),
    ),
     'locked' => false,
     'blockedVarsForExport' => 
    array (
    ),
     'fieldtype' => 'panel',
     'layout' => NULL,
     'border' => false,
     'icon' => NULL,
     'labelWidth' => 100,
     'labelAlign' => 'left',
  )),
   'icon' => 'class',
   'group' => NULL,
   'showAppLoggerTab' => false,
   'linkGeneratorReference' => '',
   'previewGeneratorReference' => '',
   'compositeIndices' => 
  array (
  ),
   'showFieldLookup' => false,
   'propertyVisibility' => 
  array (
    'grid' => 
    array (
      'id' => true,
      'path' => true,
      'published' => true,
      'modificationDate' => true,
      'creationDate' => true,
    ),
    'search' => 
    array (
      'id' => true,
      'path' => true,
      'published' => true,
      'modificationDate' => true,
      'creationDate' => true,
    ),
  ),
   'enableGridLocking' => false,
   'deletedDataComponents' => 
  array (
  ),
   'blockedVarsForExport' => 
  array (
  ),
   'fieldDefinitionsCache' => 
  array (
  ),
   'activeDispatchingEvents' => 
  array (
  ),
));
