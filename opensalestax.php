<?php
/**
 * SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later
 *
 * OpenSalesTax for PrestaShop — main module file.
 *
 * Replaces PrestaShop's built-in tax calculation with the
 * OpenSalesTax engine for destination-based US sales tax.
 *
 * @author    Eric Osterberg <ejosterberg@gmail.com>
 * @copyright 2026 Eric Osterberg
 * @license   Apache-2.0 OR GPL-2.0-or-later
 */

if (!defined('_PS_VERSION_')) {
    // PrestaShop sets _PS_VERSION_ before loading any module file. If it's
    // missing we're being loaded outside PrestaShop (a smoke test, a
    // packaging script, etc.) — abort cleanly without registering anything.
    exit;
}

// Composer autoload — packaged into the ZIP at build time. Optional in
// dev/CI where the project root composer autoload is already loaded.
$ostaxAutoload = __DIR__ . '/vendor/autoload.php';
if (is_file($ostaxAutoload)) {
    require_once $ostaxAutoload;
}

use OpenSalesTax\PrestaShop\Support\ConfigBag;

/**
 * The PrestaShop module class. PrestaShop's class loader requires the class
 * name to match the module directory name (without the `.php` extension),
 * which is `opensalestax`. PSR-12 PascalCase doesn't apply here.
 *
 * @phpstan-ignore-next-line
 */
class OpenSalesTax extends Module
{
    /**
     * Configuration keys (prefix `OSTAX_`) — written via
     * `Configuration::updateValue()`, read via `Configuration::get()`.
     */
    public const CONFIG_PREFIX = 'OSTAX_';

    public const CFG_ENABLED              = 'OSTAX_ENABLED';
    public const CFG_BASE_URL             = 'OSTAX_BASE_URL';
    public const CFG_API_KEY              = 'OSTAX_API_KEY';
    public const CFG_TIMEOUT_SECONDS      = 'OSTAX_TIMEOUT_SECONDS';
    public const CFG_TLS_VERIFY           = 'OSTAX_TLS_VERIFY';
    public const CFG_ALLOW_PRIVATE_NETS   = 'OSTAX_ALLOW_PRIVATE_NETS';
    public const CFG_FAIL_HARD            = 'OSTAX_FAIL_HARD';
    public const CFG_CACHE_TTL_SECONDS    = 'OSTAX_CACHE_TTL_SECONDS';
    public const CFG_NEXUS_FILTER_ENABLED = 'OSTAX_NEXUS_FILTER_ENABLED';
    public const CFG_NEXUS_STATE_LIST     = 'OSTAX_NEXUS_STATE_LIST';

    public function __construct()
    {
        $this->name          = 'opensalestax';
        $this->tab           = 'billing_invoicing';
        $this->version       = '0.1.0';
        $this->author        = 'Eric Osterberg';
        $this->need_instance = 0;
        $this->bootstrap     = true;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = $this->l('OpenSalesTax');
        $this->description = $this->l(
            'Destination-based US sales tax via a merchant-self-hosted ' .
            'OpenSalesTax engine. Calculation only; the merchant remits.',
        );
        $this->confirmUninstall = $this->l(
            'Uninstall OpenSalesTax? PrestaShop will fall back to its ' .
            'built-in tax tables on next cart recalc.',
        );
    }

    /**
     * Module install.
     *
     * Registers the `actionTaxManagerFactory` hook (the chosen extension
     * point — see `specs/decisions/001-taxmanager-hook.md`) and seeds safe
     * defaults into PrestaShop's `Configuration` table.
     */
    public function install(): bool
    {
        if (!parent::install()) {
            return false;
        }

        if (!$this->registerHook('actionTaxManagerFactory')) {
            return false;
        }

        // Safe defaults: disabled, fail-soft, TLS-on, private nets blocked,
        // 1h cache, no nexus filter.
        Configuration::updateValue(self::CFG_ENABLED, false);
        Configuration::updateValue(self::CFG_BASE_URL, '');
        Configuration::updateValue(self::CFG_API_KEY, '');
        Configuration::updateValue(self::CFG_TIMEOUT_SECONDS, '10');
        Configuration::updateValue(self::CFG_TLS_VERIFY, true);
        Configuration::updateValue(self::CFG_ALLOW_PRIVATE_NETS, false);
        Configuration::updateValue(self::CFG_FAIL_HARD, false);
        Configuration::updateValue(self::CFG_CACHE_TTL_SECONDS, '3600');
        Configuration::updateValue(self::CFG_NEXUS_FILTER_ENABLED, false);
        Configuration::updateValue(self::CFG_NEXUS_STATE_LIST, '');

        return true;
    }

    /**
     * Module uninstall — clean up Configuration rows.
     */
    public function uninstall(): bool
    {
        $keys = [
            self::CFG_ENABLED,
            self::CFG_BASE_URL,
            self::CFG_API_KEY,
            self::CFG_TIMEOUT_SECONDS,
            self::CFG_TLS_VERIFY,
            self::CFG_ALLOW_PRIVATE_NETS,
            self::CFG_FAIL_HARD,
            self::CFG_CACHE_TTL_SECONDS,
            self::CFG_NEXUS_FILTER_ENABLED,
            self::CFG_NEXUS_STATE_LIST,
        ];
        foreach ($keys as $key) {
            Configuration::deleteByName($key);
        }
        return parent::uninstall();
    }

    /**
     * Hook callback — `actionTaxManagerFactory`.
     *
     * Returns a `TaxManagerInterface` implementation when the cart is
     * eligible (US ship-to + USD currency + module enabled + URL valid).
     * Returns null otherwise so PrestaShop falls back to its default
     * `GlobalTaxManager`.
     *
     * The actual override class lives in `src/PrestaShop/TaxManagerOverride.php`
     * and is loaded via Composer autoload. v0.1 ships the wiring stub; the
     * concrete implementation lands in Phase 02 once we've validated the
     * TaxManagerInterface signature against a live PrestaShop install.
     *
     * @param array<string, mixed> $params PrestaShop hook payload — has
     *     `address` (Address), `tax_rules_group_id` (int), `type` (string).
     */
    public function hookActionTaxManagerFactory(array $params): mixed
    {
        // v0.1 stub — the live wiring lands once integration-test feedback
        // confirms the TaxManagerInterface signature in PrestaShop 8.x core.
        // For now, return null so PrestaShop falls back to its default
        // tax flow. Tests cover the framework-agnostic core via
        // `tests/Unit/Support/`.
        return null;
    }

    /**
     * Build a `ConfigBag` from PrestaShop's Configuration table.
     *
     * Public so the (future) Phase 02 admin "Test Connection" button can
     * read the same bag as the runtime hook.
     */
    public function buildConfigBag(): ConfigBag
    {
        return ConfigBag::fromArray([
            'enabled'               => (bool) Configuration::get(self::CFG_ENABLED),
            'base_url'              => (string) Configuration::get(self::CFG_BASE_URL),
            'api_key'               => (string) Configuration::get(self::CFG_API_KEY),
            'timeout_seconds'       => (string) Configuration::get(self::CFG_TIMEOUT_SECONDS),
            'tls_verify'            => (bool) Configuration::get(self::CFG_TLS_VERIFY),
            'allow_private_nets'    => (bool) Configuration::get(self::CFG_ALLOW_PRIVATE_NETS),
            'fail_hard'             => (bool) Configuration::get(self::CFG_FAIL_HARD),
            'cache_ttl_seconds'     => (string) Configuration::get(self::CFG_CACHE_TTL_SECONDS),
            'nexus_filter_enabled'  => (bool) Configuration::get(self::CFG_NEXUS_FILTER_ENABLED),
            'nexus_state_allowlist' => (string) Configuration::get(self::CFG_NEXUS_STATE_LIST),
        ]);
    }

    /**
     * Render the admin settings form. PrestaShop calls this when the merchant
     * opens Modules → OpenSalesTax → Configure.
     *
     * v0.1 ships a minimal form. Phase 02 polishes (Test Connection button,
     * inline validation, helper copy).
     */
    public function getContent(): string
    {
        $output = '';
        if (Tools::isSubmit('submit_' . $this->name)) {
            $output .= $this->processSettingsForm();
        }
        return $output . $this->renderSettingsForm();
    }

    /**
     * Save the admin form. URL is validated via `UrlValidator` (SSRF
     * defense); rejection surfaces as an admin error.
     */
    private function processSettingsForm(): string
    {
        $baseUrl    = trim((string) Tools::getValue(self::CFG_BASE_URL));
        $allowPriv  = (bool) Tools::getValue(self::CFG_ALLOW_PRIVATE_NETS);

        if ($baseUrl !== '') {
            try {
                (new \OpenSalesTax\PrestaShop\Support\UrlValidator($allowPriv))->validate($baseUrl);
            } catch (\InvalidArgumentException $e) {
                return $this->displayError($this->l('Engine URL rejected: ') . $e->getMessage());
            }
        }

        Configuration::updateValue(self::CFG_ENABLED, (bool) Tools::getValue(self::CFG_ENABLED));
        Configuration::updateValue(self::CFG_BASE_URL, $baseUrl);
        Configuration::updateValue(self::CFG_API_KEY, (string) Tools::getValue(self::CFG_API_KEY));
        Configuration::updateValue(self::CFG_TIMEOUT_SECONDS, (string) Tools::getValue(self::CFG_TIMEOUT_SECONDS));
        Configuration::updateValue(self::CFG_TLS_VERIFY, (bool) Tools::getValue(self::CFG_TLS_VERIFY));
        Configuration::updateValue(self::CFG_ALLOW_PRIVATE_NETS, $allowPriv);
        Configuration::updateValue(self::CFG_FAIL_HARD, (bool) Tools::getValue(self::CFG_FAIL_HARD));
        Configuration::updateValue(self::CFG_CACHE_TTL_SECONDS, (string) Tools::getValue(self::CFG_CACHE_TTL_SECONDS));
        Configuration::updateValue(self::CFG_NEXUS_FILTER_ENABLED, (bool) Tools::getValue(self::CFG_NEXUS_FILTER_ENABLED));
        Configuration::updateValue(self::CFG_NEXUS_STATE_LIST, (string) Tools::getValue(self::CFG_NEXUS_STATE_LIST));

        return $this->displayConfirmation($this->l('Settings saved.'));
    }

    /**
     * Build the HelperForm-rendered admin form.
     */
    private function renderSettingsForm(): string
    {
        $helper = new HelperForm();
        $helper->module          = $this;
        $helper->name_controller = $this->name;
        $helper->token           = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex    = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->submit_action   = 'submit_' . $this->name;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        $helper->fields_value = [
            self::CFG_ENABLED              => (bool) Configuration::get(self::CFG_ENABLED),
            self::CFG_BASE_URL             => (string) Configuration::get(self::CFG_BASE_URL),
            self::CFG_API_KEY              => (string) Configuration::get(self::CFG_API_KEY),
            self::CFG_TIMEOUT_SECONDS      => (string) Configuration::get(self::CFG_TIMEOUT_SECONDS),
            self::CFG_TLS_VERIFY           => (bool) Configuration::get(self::CFG_TLS_VERIFY),
            self::CFG_ALLOW_PRIVATE_NETS   => (bool) Configuration::get(self::CFG_ALLOW_PRIVATE_NETS),
            self::CFG_FAIL_HARD            => (bool) Configuration::get(self::CFG_FAIL_HARD),
            self::CFG_CACHE_TTL_SECONDS    => (string) Configuration::get(self::CFG_CACHE_TTL_SECONDS),
            self::CFG_NEXUS_FILTER_ENABLED => (bool) Configuration::get(self::CFG_NEXUS_FILTER_ENABLED),
            self::CFG_NEXUS_STATE_LIST     => (string) Configuration::get(self::CFG_NEXUS_STATE_LIST),
        ];

        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('OpenSalesTax — engine settings'),
                    'icon'  => 'icon-cogs',
                ],
                'description' => $this->l(
                    'Tax calculations are provided as-is for convenience. The ' .
                    'merchant is solely responsible for tax-collection accuracy ' .
                    'and remittance to the appropriate jurisdictions. Verify ' .
                    'against your state Department of Revenue before remitting.',
                ),
                'input' => [
                    [
                        'type'    => 'switch',
                        'label'   => $this->l('Enabled'),
                        'name'    => self::CFG_ENABLED,
                        'is_bool' => true,
                        'values'  => $this->yesNoSwitch(),
                    ],
                    [
                        'type'     => 'text',
                        'label'    => $this->l('Engine base URL'),
                        'name'     => self::CFG_BASE_URL,
                        'desc'     => $this->l('Fully-qualified URL to your OpenSalesTax engine (e.g. https://ost.example.com).'),
                        'required' => false,
                    ],
                    [
                        'type'  => 'text',
                        'label' => $this->l('API key (optional)'),
                        'name'  => self::CFG_API_KEY,
                    ],
                    [
                        'type'  => 'text',
                        'label' => $this->l('Timeout (seconds)'),
                        'name'  => self::CFG_TIMEOUT_SECONDS,
                    ],
                    [
                        'type'    => 'switch',
                        'label'   => $this->l('Verify TLS certificates'),
                        'name'    => self::CFG_TLS_VERIFY,
                        'is_bool' => true,
                        'values'  => $this->yesNoSwitch(),
                    ],
                    [
                        'type'    => 'switch',
                        'label'   => $this->l('Allow private-network engines (LAN / VPN)'),
                        'name'    => self::CFG_ALLOW_PRIVATE_NETS,
                        'is_bool' => true,
                        'desc'    => $this->l('Default off. Enable only if your OST engine is on a private network you control.'),
                        'values'  => $this->yesNoSwitch(),
                    ],
                    [
                        'type'    => 'switch',
                        'label'   => $this->l('Fail hard on engine errors'),
                        'name'    => self::CFG_FAIL_HARD,
                        'is_bool' => true,
                        'desc'    => $this->l('Default off (fail-soft). When on, an unreachable engine blocks checkout.'),
                        'values'  => $this->yesNoSwitch(),
                    ],
                    [
                        'type'  => 'text',
                        'label' => $this->l('Cache TTL (seconds)'),
                        'name'  => self::CFG_CACHE_TTL_SECONDS,
                    ],
                    [
                        'type'    => 'switch',
                        'label'   => $this->l('Enable nexus filter'),
                        'name'    => self::CFG_NEXUS_FILTER_ENABLED,
                        'is_bool' => true,
                        'desc'    => $this->l('When on, only ship-to addresses in the allowlisted states get destination-based tax.'),
                        'values'  => $this->yesNoSwitch(),
                    ],
                    [
                        'type'  => 'text',
                        'label' => $this->l('Nexus state allowlist (comma-separated 2-letter codes, e.g. MN,CA,NY)'),
                        'name'  => self::CFG_NEXUS_STATE_LIST,
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];

        return $helper->generateForm([['form' => $form['form']]]);
    }

    /**
     * @return list<array{id: string, value: int, label: string}>
     */
    private function yesNoSwitch(): array
    {
        return [
            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Yes')],
            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('No')],
        ];
    }
}
