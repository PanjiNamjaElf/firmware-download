<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\SoftwareVersion;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;

/**
 * EasyAdmin CRUD for SoftwareVersion records.
 *
 * Built for non-technical internal users — every field has a plain-English label and
 * help text. "Is Latest" is restricted to one entry per product name via a validator.
 * LCI HW Type is a dropdown to avoid free-text typos.
 */
class SoftwareVersionCrudController extends AbstractCrudController
{
    /**
     * LCI hardware type options.
     * Used in both the form ChoiceField and the list ChoiceFilter.
     */
    private const LCI_HW_TYPE_CHOICES = [
        'CIC — Older iDrive system' => 'CIC',
        'NBT — Mid-generation iDrive system' => 'NBT',
        'EVO — Latest iDrive system' => 'EVO',
    ];

    public static function getEntityFqcn(): string
    {
        return SoftwareVersion::class;
    }

    /**
     * Page titles, search fields, default sort, and per-page count.
     */
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Firmware Version')
            ->setEntityLabelInPlural('Firmware Versions')
            ->setPageTitle(Crud::PAGE_INDEX, '📦 All Firmware Versions')
            ->setPageTitle(Crud::PAGE_NEW, '➕ Add New Firmware Version')
            ->setPageTitle(Crud::PAGE_EDIT, 'Edit Firmware Version')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Firmware Version Details')
            ->setHelp(
                Crud::PAGE_INDEX,
                '⚠️ <strong>Important:</strong> This data controls which firmware file customers receive. '
                .'Please double-check all entries before saving. '
                .'Use the filters on the right to find specific records quickly.',
            )
            ->setHelp(
                Crud::PAGE_NEW,
                '⚠️ <strong>Before adding a new version:</strong> Make sure the product name exactly matches '
                .'an existing product name (e.g. "MMI Prime CIC"). Check the list page to confirm the correct spelling.',
            )
            ->setHelp(
                Crud::PAGE_EDIT,
                '⚠️ <strong>Before saving:</strong> Verify that the version strings and download links are correct. '
                .'Wrong firmware sent to customers can damage their device.',
            )
            ->setDefaultSort(['sortOrder' => 'ASC', 'name' => 'ASC'])
            ->setSearchFields(['name', 'systemVersion', 'systemVersionAlt'])
            ->setPaginatorPageSize(25)
            ->showEntityActionsInlined();
    }

    /**
     * Action button labels for list and form pages.
     */
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::NEW, static fn(Action $a) => $a->setLabel('Add New Version'))
            ->update(Crud::PAGE_INDEX, Action::EDIT, static fn(Action $a) => $a->setLabel('Edit'))
            ->update(Crud::PAGE_INDEX, Action::DELETE, static fn(Action $a) => $a->setLabel('Delete'))
            ->update(Crud::PAGE_INDEX, Action::DETAIL, static fn(Action $a) => $a->setLabel('View'))
            ->update(Crud::PAGE_NEW, Action::SAVE_AND_RETURN, static fn(Action $a) => $a->setLabel('Save version'))
            ->update(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER, static fn(Action $a) => $a->setLabel('Save and add another'))
            ->update(Crud::PAGE_EDIT, Action::SAVE_AND_RETURN, static fn(Action $a) => $a->setLabel('Save changes'))
            ->update(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE, static fn(Action $a) => $a->setLabel('Save and keep editing'));
    }

    /**
     * Sidebar filters for auditing "latest" flags, LCI status, and hardware type.
     */
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(
                BooleanFilter::new('isLatest', 'Show only latest versions')
                    ->setFormTypeOption('label', 'Latest versions only'),
            )
            ->add(
                BooleanFilter::new('isLci', 'Device family')
                    ->setFormTypeOption('label', 'LCI devices only'),
            )
            ->add(
                ChoiceFilter::new('lciHwType', 'LCI Hardware System')
                    ->setChoices(self::LCI_HW_TYPE_CHOICES),
            );
    }

    /**
     * Form fields and list columns. Grouped into panels with full help text for
     * non-technical users. Each panel matches a logical area of the firmware record.
     */
    public function configureFields(string $pageName): iterable
    {
        // -----------------------------------------------------------------
        // LIST and DETAIL columns — concise, scannable.
        // -----------------------------------------------------------------

        if ($pageName === Crud::PAGE_INDEX) {
            yield IntegerField::new('sortOrder', '#')
                ->setSortable(true);

            yield TextField::new('name', 'Product Name')
                ->setSortable(true);

            yield TextField::new('systemVersion', 'Version (display)')
                ->setSortable(true);

            yield BooleanField::new('isLci', 'LCI?')
                ->renderAsSwitch(false)
                ->setSortable(true);

            yield ChoiceField::new('lciHwType', 'HW System')
                ->setChoices(self::LCI_HW_TYPE_CHOICES)
                ->formatValue(static fn (?string $value): string => $value ?? '—')
                ->setSortable(true);

            yield BooleanField::new('isLatest', '✅ Latest?')
                ->renderAsSwitch(false)
                ->setSortable(true);

            return;
        }

        if ($pageName === Crud::PAGE_DETAIL) {
            yield TextField::new('name', 'Product Name');
            yield TextField::new('systemVersion', 'Version (display)');
            yield TextField::new('systemVersionAlt', 'Version (matching)');
            yield BooleanField::new('isLci', 'LCI Device')->renderAsSwitch(false);
            yield TextField::new('lciHwType', 'LCI Hardware System');
            yield BooleanField::new('isLatest', 'Is Latest Version')->renderAsSwitch(false);
            yield UrlField::new('link', 'General Download Link');
            yield UrlField::new('stLink', 'ST Version Link');
            yield UrlField::new('gdLink', 'GD Version Link');
            yield IntegerField::new('sortOrder', 'Sort Order');
            yield DateTimeField::new('createdAt', 'Created');
            yield DateTimeField::new('updatedAt', 'Last Updated');

            return;
        }

        // -----------------------------------------------------------------
        // NEW and EDIT form — grouped panels with full help text.
        // -----------------------------------------------------------------

        // ---- Panel 1: Product Name -------------------------------------------
        yield FormField::addPanel('📋 Product Name')
            ->setHelp(
                'Identify which product name this version belongs to. '
                .'The product name must exactly match the existing entries for the same line.'
            );

        yield TextField::new('name', 'Product Name')
            ->setRequired(true)
            ->setHelp(
                'The name of the name this version belongs to. '
                .'<strong>Copy the exact name from an existing entry in the same product name</strong> '
                .'(check the list page). Examples: <code>MMI Prime CIC</code>, <code>LCI MMI PRO NBT</code>. '
                .'A typo here will cause this version to never be matched.',
            )
            ->setColumns(6);

        yield IntegerField::new('sortOrder', 'Sort Order')
            ->setRequired(true)
            ->setHelp(
                'Controls the order in which records are checked. '
                .'Lower numbers are checked first. For a new version in an existing product name, '
                .'use a number higher than the current latest entry for that line. '
                .'Example: if the highest sort order for "MMI Prime CIC" is 18, use 19.',
            )
            ->setColumns(6);

        // ---- Panel 2: Version Strings ----------------------------------------
        yield FormField::addPanel('🔢 Version Strings')
            ->setHelp('Enter the version number exactly as it appears on the device screen.');

        yield TextField::new('systemVersion', 'Version')
            ->setRequired(true)
            ->setHelp(
                'The full version string <strong>including</strong> the leading "v", exactly as shown on the device. '
                .'Example: <code>v3.3.7.mmipri.c</code>. '
                .'The internal matching key (without "v") is derived automatically.',
            )
            ->setColumns(6);

        // ---- Panel 3: Hardware Classification --------------------------------
        yield FormField::addPanel('🖥️ Hardware Classification')
            ->setHelp(
                'These settings determine which customers this version applies to. '
                .'Getting these wrong will cause the wrong firmware to be shown — or no firmware at all.'
            );

        yield BooleanField::new('isLci', 'Is this an LCI device?')
            ->setHelp(
                'Check this box <strong>only</strong> if this entry is for an LCI (Life Cycle Impulse) device. '
                .'LCI product names always start with "LCI" (e.g. "LCI MMI Prime CIC"). '
                .'Standard devices (no LCI prefix) must have this unchecked. '
                .'If you are unsure, check the product name on the list page.',
            )
            ->setColumns(12)
            ->renderAsSwitch();

        yield ChoiceField::new('lciHwType', 'LCI Hardware System')
            ->setChoices(self::LCI_HW_TYPE_CHOICES)
            ->setRequired(false)
            ->allowMultipleChoices(false)
            ->renderExpanded(false)
            ->setHelp(
                'Required for LCI devices only — leave empty for standard devices. '
                .'Choose the correct hardware system type: '
                .'<strong>CIC</strong> = older iDrive (square screen), '
                .'<strong>NBT</strong> = mid-generation iDrive, '
                .'<strong>EVO</strong> = latest iDrive (touchscreen). '
                .'This determines which download link is sent to the customer.',
            )
            ->setColumns(6)
            ->setFormTypeOption('placeholder', '— Leave empty for standard (non-LCI) devices —');

        // ---- Panel 4: Download Links -----------------------------------------
        yield FormField::addPanel('🔗 Download Links')
            ->setHelp(
                'Enter the Google Drive folder links for the firmware files. '
                .'ST and GD refer to the chip manufacturer inside the device — '
                .'customers will only ever see the link that matches their own hardware chip. '
                .'Leave fields empty if they do not apply to this entry.'
            );

        yield UrlField::new('link', 'General Download Link')
            ->setRequired(false)
            ->setHelp(
                'The main Google Drive folder for this firmware version. '
                .'Used as a fallback or reference link. Usually empty for LCI entries. '
                .'Must be a full URL starting with <code>https://</code>.',
            )
            ->setColumns(12);

        yield UrlField::new('stLink', 'ST Version Download Link')
            ->setRequired(false)
            ->setHelp(
                'Download link for customers with the <strong>ST (STMicroelectronics)</strong> chip. '
                .'Customers with an ST device will receive this link. '
                .'Leave empty if ST chip is not applicable for this entry or version. '
                .'Must be a full URL starting with <code>https://</code>.',
            )
            ->setColumns(6);

        yield UrlField::new('gdLink', 'GD Version Download Link')
            ->setRequired(false)
            ->setHelp(
                'Download link for customers with the <strong>GD (GigaDevice)</strong> chip. '
                .'Customers with a GD device will receive this link. '
                .'Leave empty if GD chip is not applicable for this entry or version. '
                .'Must be a full URL starting with <code>https://</code>.',
            )
            ->setColumns(6);

        // ---- Panel 5: Status -------------------------------------------------
        yield FormField::addPanel('🚦 Version Status')
            ->setHelp(
                '⚠️ <strong>Read carefully before changing this setting.</strong>'
            );

        yield BooleanField::new('isLatest', 'Is this the latest version for this product name?')
            ->setHelp(
                '⚠️ <strong>Only ONE entry per product name should ever be marked as latest.</strong> '
                .'When a customer is on the latest version, they will see "Your system is up to date" '
                .'and will NOT receive any download link. '
                .'When you release a new firmware version: '
                .'(1) uncheck "Is Latest" on the current latest entry, '
                .'(2) add the new entry, '
                .'(3) check "Is Latest" on the new entry. '
                .'Latest entries should have empty ST/GD download links.',
            )
            ->setColumns(12)
            ->renderAsSwitch();
    }
}
