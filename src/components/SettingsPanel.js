import { useState, useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

// ---------------------------------------------------------------------------
// Provider definitions (mirrors backend CDW_AI_Service::get_providers)
// ---------------------------------------------------------------------------
const PROVIDERS = [
    {
        id: 'openai',
        label: 'OpenAI',
        icon: '🤖',
        description: 'GPT-4o, GPT-4o-mini and more',
        keyPlaceholder: 'sk-…',
        models: [
            { id: 'gpt-4o', label: 'GPT-4o' },
            { id: 'gpt-4o-mini', label: 'GPT-4o mini (fast)' },
            { id: 'gpt-4-turbo', label: 'GPT-4 Turbo' },
        ],
    },
    {
        id: 'anthropic',
        label: 'Anthropic',
        icon: '🧠',
        description: 'Claude 3.5 Sonnet, Haiku and more',
        keyPlaceholder: 'sk-ant-…',
        models: [
            { id: 'claude-3-5-sonnet-20241022', label: 'Claude 3.5 Sonnet' },
            { id: 'claude-3-5-haiku-20241022', label: 'Claude 3.5 Haiku (fast)' },
            { id: 'claude-3-opus-20240229', label: 'Claude 3 Opus' },
        ],
    },
    {
        id: 'google',
        label: 'Google',
        icon: '✦',
        description: 'Gemini 2.0 Flash and more',
        keyPlaceholder: 'AIzaSy…',
        models: [
            { id: 'gemini-2.0-flash', label: 'Gemini 2.0 Flash (fast)' },
            { id: 'gemini-1.5-pro', label: 'Gemini 1.5 Pro' },
            { id: 'gemini-1.5-flash', label: 'Gemini 1.5 Flash' },
        ],
    },
    {
        id: 'custom',
        label: 'Custom',
        icon: '🔌',
        description: 'OpenAI-compatible endpoint (Groq, OpenRouter…)',
        keyPlaceholder: 'API key…',
        models: [
            { id: 'custom', label: 'Enter model name below' },
        ],
        customUrl: true,
        customModel: true,
    },
];

export default function SettingsPanel() {
    const settings = useSelect((select) => select('cdw/store').getSettings());
    const isLoading = useSelect((select) => select('cdw/store').isLoading('settings'));
    const { fetchSettings, saveSettings } = useDispatch('cdw/store');

    // General settings form
    const [formData, setFormData] = useState({
        email: '',
        docs_url: '',
        font_size: '',
        bg_color: '',
        header_bg_color: '',
        header_text_color: '',
        cli_enabled: true,
        remove_default_widgets: true,
        delete_on_uninstall: true,
        ai_enabled: false,
        ai_execution_mode: 'confirm',
        ai_custom_system_prompt: '',
        mcp_public: false,
        user_type: null,
    });

    const [saved, setSaved] = useState(false);
    const [saveError, setSaveError] = useState(null);

    // AI per-user settings
    const [aiSettings, setAiSettings] = useState(null);
    const [aiSettingsLoading, setAiSettingsLoading] = useState(false);

    // AI form state
    const [selectedProvider, setSelectedProvider] = useState('openai');
    const [selectedModel, setSelectedModel] = useState('');
    const [apiKey, setApiKey] = useState('');
    const [baseUrl, setBaseUrl] = useState('');
    const [customModelName, setCustomModelName] = useState('');
    const [aiSaving, setAiSaving] = useState(false);
    const [aiSaved, setAiSaved] = useState(false);
    const [aiSaveError, setAiSaveError] = useState(null);

    // Test connection
    const [testing, setTesting] = useState(false);
    const [testResult, setTestResult] = useState(null); // null | { ok: bool, message: string }

    // Usage stats
    const [usage, setUsage] = useState(null);
    const [usageLoading, setUsageLoading] = useState(false);
    const [usageResetting, setUsageResetting] = useState(false);
    const [usageResetDone, setUsageResetDone] = useState(false);

    useEffect(() => {
        fetchSettings();
        loadAiSettings();
        loadUsage();
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => {
        if (settings) {
            setFormData({
                email: settings.email || '',
                docs_url: settings.docs_url || '',
                font_size: settings.font_size || '',
                bg_color: settings.bg_color || '',
                header_bg_color: settings.header_bg_color || '',
                header_text_color: settings.header_text_color || '',
                cli_enabled: settings.cli_enabled !== false,
                remove_default_widgets: settings.remove_default_widgets !== false,
                delete_on_uninstall: settings.delete_on_uninstall !== false,
                ai_enabled: settings.ai_enabled === true,
                ai_execution_mode: settings.ai_execution_mode || 'confirm',
                ai_custom_system_prompt: settings.ai_custom_system_prompt || '',
                mcp_public: settings.mcp_public === true,
                user_type: settings.user_type || null,
            });
        }
    }, [settings]);

    const loadAiSettings = async () => {
        setAiSettingsLoading(true);
        try {
            const result = await apiFetch({ path: '/cdw/v1/ai/settings' });
            const settings = result.data || result;
            setAiSettings(settings);
            setSelectedProvider(settings.provider || 'openai');
            setSelectedModel(settings.model || '');
            setBaseUrl(settings.base_url || '');
            // If the model is not in the predefined list, treat it as custom model name
            const providerDef = PROVIDERS.find((p) => p.id === (settings.provider || 'openai'));
            if (providerDef && providerDef.customModel && settings.model && settings.model !== 'custom') {
                setCustomModelName(settings.model);
            }
        } catch (e) {
            // AI endpoints may not be available yet
        } finally {
            setAiSettingsLoading(false);
        }
    };

    const loadUsage = async () => {
        setUsageLoading(true);
        try {
            const result = await apiFetch({ path: '/cdw/v1/ai/usage' });
            setUsage(result.data || result);
        } catch (e) {
            // Non-fatal
        } finally {
            setUsageLoading(false);
        }
    };
    const resetUsage = async () => {
        if ( ! window.confirm( 'Reset all AI usage statistics? This cannot be undone.' ) ) {
            return;
        }
        setUsageResetting(true);
        try {
            await apiFetch({ path: '/cdw/v1/ai/usage', method: 'DELETE' });
            setUsage(null);
            setUsageResetDone(true);
            setTimeout(() => setUsageResetDone(false), 3000);
        } catch (e) {
            // silently ignore
        } finally {
            setUsageResetting(false);
        }
    };
    const handleChange = (e) => {
        const { name, value, type, checked } = e.target;
        setFormData((prev) => ({
            ...prev,
            [name]: type === 'checkbox' ? checked : value,
        }));
        setSaved(false);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaveError(null);
        try {
            await saveSettings(formData);
            setSaved(true);
            setTimeout(() => setSaved(false), 3000);
        } catch (err) {
            setSaveError(err.message || 'Failed to save settings.');
        }
    };

    const handleAiSave = async (e) => {
        e.preventDefault();
        setAiSaving(true);
        setAiSaveError(null);
        setAiSaved(false);
        setTestResult(null);

        const providerDef = PROVIDERS.find((p) => p.id === selectedProvider);
        const effectiveModel = providerDef && providerDef.customModel
            ? customModelName
            : selectedModel;

        const payload = {
            provider: selectedProvider,
            model: effectiveModel,
        };
        if (providerDef && providerDef.customUrl) {
            payload.base_url = baseUrl;
        }
        if (apiKey && apiKey.trim()) {
            payload.api_key = apiKey.trim();
        }

        try {
            await apiFetch({
                path: '/cdw/v1/ai/settings',
                method: 'POST',
                data: payload,
            });
            setAiSaved(true);
            setApiKey(''); // clear after save (key is write-only)
            await loadAiSettings(); // refresh has_key indicator
            setTimeout(() => setAiSaved(false), 3000);
        } catch (err) {
            setAiSaveError(err.message || 'Failed to save AI settings.');
        } finally {
            setAiSaving(false);
        }
    };

    const handleTestConnection = async () => {
        setTesting(true);
        setTestResult(null);
        try {
            await apiFetch({
                path: '/cdw/v1/ai/test',
                method: 'POST',
                data: { provider: selectedProvider },
            });
            setTestResult({ ok: true, message: 'Connected successfully!' });
        } catch (err) {
            const msg = err.message || (err.data && err.data.message) || 'Connection failed';
            setTestResult({ ok: false, message: msg });
        } finally {
            setTesting(false);
        }
    };

    const currentProviderDef = PROVIDERS.find((p) => p.id === selectedProvider) || PROVIDERS[0];

    return (
        <div className="cdw-settings-panel">
            <div className="cdw-settings-header">
                <h1>Custom Dashboard Widgets</h1>
                <p>Configure your dashboard widgets and appearance settings</p>
            </div>

            {/* ============================================================
                User Type (for resetting onboarding)
            ============================================================ */}
            {formData.user_type && (
                <div className="cdw-settings-section cdw-user-type-section">
                    <div className="cdw-section-header">
                        <div className="cdw-section-icon">&#128100;</div>
                        <h2>Onboarding</h2>
                    </div>

                    <div className="cdw-field">
                        <label className="cdw-checkbox-label">
                            <input
                                type="checkbox"
                                name="is_developer"
                                checked={formData.user_type === 'developer'}
                                onChange={(e) => {
                                    const newType = e.target.checked ? 'developer' : 'user';
                                    setFormData((prev) => ({
                                        ...prev,
                                        user_type: newType,
                                    }));
                                    setSaved(false);
                                }}
                            />
                            <span>I'm a developer (skip onboarding)</span>
                        </label>
                        <span className="description">
                            Check this if you're comfortable with the plugin and don't need the welcome guide.
                            {' '}
                            <a href={cdwData.adminUrl + 'tools.php?page=cdw-welcome'}>View welcome page</a>
                        </span>
                    </div>
                </div>
            )}

            <form onSubmit={handleSubmit}>
                {/* ============================================================
                    Contact Information
                ============================================================ */}
                <div className="cdw-settings-section cdw-contact-section">
                    <div className="cdw-section-header">
                        <div className="cdw-section-icon">&#9993;</div>
                        <h2>Contact Information</h2>
                    </div>

                    <div className="cdw-field">
                        <label htmlFor="email">Support Email</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value={formData.email}
                            onChange={handleChange}
                            placeholder="support@yourcompany.com"
                        />
                        <span className="description">
                            This email will be displayed in the Help widget for users to contact support
                        </span>
                    </div>

                    <div className="cdw-field">
                        <label htmlFor="docs_url">Documentation URL</label>
                        <input
                            type="url"
                            id="docs_url"
                            name="docs_url"
                            value={formData.docs_url}
                            onChange={handleChange}
                            placeholder="https://yourcompany.com/docs"
                        />
                        <span className="description">
                            Link to your documentation or help center
                        </span>
                    </div>
                </div>

                {/* ============================================================
                    Widget Appearance
                ============================================================ */}
                <div className="cdw-settings-section cdw-appearance-section">
                    <div className="cdw-section-header">
                        <div className="cdw-section-icon">&#127912;</div>
                        <h2>Widget Appearance</h2>
                    </div>

                    <div className="cdw-field">
                        <label htmlFor="font_size">Widget Text Size (px)</label>
                        <input
                            type="number"
                            id="font_size"
                            name="font_size"
                            value={formData.font_size}
                            onChange={handleChange}
                            min="10"
                            max="40"
                            placeholder="Leave empty for default"
                        />
                        <span className="description">
                            Choose a custom font size in pixels. Leave empty to use WordPress default.
                        </span>
                    </div>

                    <div className="cdw-field">
                        <label htmlFor="bg_color">Widget Background Color</label>
                        <input
                            type="text"
                            id="bg_color"
                            name="bg_color"
                            value={formData.bg_color}
                            onChange={handleChange}
                            placeholder="#ffffff"
                            pattern="^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$"
                            title="Enter a valid hex color (e.g. #ffffff or #fff), or leave empty for default"
                        />
                        <span className="description">
                            Hex color for widget backgrounds. Leave empty for default.
                        </span>
                    </div>

                    <div className="cdw-field">
                        <label htmlFor="header_bg_color">Widget Header Background</label>
                        <input
                            type="text"
                            id="header_bg_color"
                            name="header_bg_color"
                            value={formData.header_bg_color}
                            onChange={handleChange}
                            placeholder="#ff7e5f"
                            pattern="^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$"
                            title="Enter a valid hex color (e.g. #ffffff or #fff), or leave empty for default"
                        />
                        <span className="description">
                            Hex color for widget header background. Overrides the default gradient when set.
                        </span>
                    </div>

                    <div className="cdw-field">
                        <label htmlFor="header_text_color">Widget Header Text Color</label>
                        <input
                            type="text"
                            id="header_text_color"
                            name="header_text_color"
                            value={formData.header_text_color}
                            onChange={handleChange}
                            placeholder="#ffffff"
                            pattern="^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$"
                            title="Enter a valid hex color (e.g. #ffffff or #fff), or leave empty for default"
                        />
                        <span className="description">
                            Hex color for widget header text. Leave empty for default.
                        </span>
                    </div>
                </div>

                {/* ============================================================
                    Command Line Widget
                ============================================================ */}
                <div className="cdw-settings-section cdw-cli-section">
                    <div className="cdw-section-header">
                        <div className="cdw-section-icon">&#128187;</div>
                        <h2>Command Line Widget</h2>
                    </div>

                    <div className="cdw-field">
                        <label htmlFor="cli_enabled" className="cdw-checkbox-label">
                            <input
                                type="checkbox"
                                id="cli_enabled"
                                name="cli_enabled"
                                checked={formData.cli_enabled}
                                onChange={handleChange}
                            />
                            <span>Enable Command Line Widget</span>
                        </label>
                        <span className="description">
                            Show the Command Line widget on the dashboard. Administrators can use it to manage plugins, themes, users, and more via CLI commands.
                        </span>
                    </div>

                    <div className="cdw-field">
                        <label htmlFor="remove_default_widgets" className="cdw-checkbox-label">
                            <input
                                type="checkbox"
                                id="remove_default_widgets"
                                name="remove_default_widgets"
                                checked={formData.remove_default_widgets}
                                onChange={handleChange}
                            />
                            <span>Remove Default WordPress Widgets</span>
                        </label>
                        <span className="description">
                            Remove the default WordPress dashboard widgets (Right Now, Activity, Quick Press, etc.) and replace them with custom widgets.
                        </span>
                    </div>
                </div>

                {/* ============================================================
                    Data Management
                ============================================================ */}
                <div className="cdw-settings-section">
                    <div className="cdw-section-header">
                        <div className="cdw-section-icon">&#128465;</div>
                        <h2>Data Management</h2>
                    </div>

                    <div className="cdw-field">
                        <label htmlFor="delete_on_uninstall" className="cdw-checkbox-label">
                            <input
                                type="checkbox"
                                id="delete_on_uninstall"
                                name="delete_on_uninstall"
                                checked={formData.delete_on_uninstall}
                                onChange={handleChange}
                            />
                            <span>Delete all data on uninstall</span>
                        </label>
                        <span className="description">
                            When the plugin is deleted (not just deactivated), remove all settings, tasks, CLI history, audit logs, and the database table. Uncheck to preserve your data across reinstalls.
                        </span>
                    </div>

                    <div className="cdw-field">
                        <label className="cdw-checkbox-label">
                            <input
                                type="checkbox"
                                name="mcp_public"
                                checked={!!formData.mcp_public}
                                onChange={handleChange}
                            />
                            <span>Expose CDW tools via MCP Adapter</span>
                        </label>
                        <span className="description">
                            When enabled, all CDW abilities are marked as public for the WordPress MCP
                            Adapter default server. Requires the{' '}
                            <a href="https://github.com/WordPress/mcp-adapter/releases" target="_blank" rel="noreferrer">
                                MCP Adapter plugin
                            </a>
                            {' '}to be installed. Only enable on trusted, secured sites.
                        </span>
                    </div>
                </div>

                {/* ============================================================
                    AI Assistant — Site-wide settings
                ============================================================ */}
                <div id="cdw-ai-settings" className="cdw-settings-section cdw-ai-section">
                    <div className="cdw-section-header">
                        <div className="cdw-section-icon">&#10022;</div>
                        <h2>AI Assistant — Site Settings</h2>
                    </div>

                    <div className="cdw-field">
                        <label htmlFor="ai_enabled" className="cdw-checkbox-label">
                            <input
                                type="checkbox"
                                id="ai_enabled"
                                name="ai_enabled"
                                checked={formData.ai_enabled}
                                onChange={handleChange}
                            />
                            <span>Enable AI Assistant</span>
                        </label>
                        <span className="description">
                            Show the AI mode toggle in the Command widget. When enabled, users can switch between CLI commands and natural-language AI requests.
                        </span>
                    </div>

                    <div className="cdw-field">
                        <label htmlFor="ai_execution_mode">Default Execution Mode</label>
                        <select
                            id="ai_execution_mode"
                            name="ai_execution_mode"
                            value={formData.ai_execution_mode}
                            onChange={handleChange}
                            className="cdw-select"
                        >
                            <option value="auto">Auto-execute — AI runs commands automatically</option>
                            <option value="confirm">Confirm first — Review before each request is sent</option>
                        </select>
                        <span className="description">
                            In <strong>Confirm first</strong> mode a confirmation banner appears before the AI request is dispatched, letting you review or cancel.
                        </span>
                    </div>

                    <div className="cdw-field">
                        <label htmlFor="ai_custom_system_prompt">Custom System Prompt</label>
                        <textarea
                            id="ai_custom_system_prompt"
                            name="ai_custom_system_prompt"
                            value={formData.ai_custom_system_prompt}
                            onChange={handleChange}
                            rows={4}
                            placeholder="Additional instructions appended to the AI system prompt…"
                            className="cdw-textarea"
                        />
                        <span className="description">
                            Optional extra instructions appended to the AI system prompt. Use this to restrict or guide the AI for your organisation's needs.
                        </span>
                    </div>
                </div>

                <div className="cdw-settings-actions">
                    <button type="submit" className="button button-primary" disabled={isLoading}>
                        {isLoading ? 'Saving...' : 'Save Changes'}
                    </button>
                    {saved && <span className="cdw-saved-message">Settings saved!</span>}
                    {saveError && <div className="cdw-error">{saveError}</div>}
                </div>
            </form>

            {/* ================================================================
                AI Assistant — Per-user settings (API keys, provider, model)
            ================================================================ */}
            <div id="cdw-ai-keys" className="cdw-settings-section cdw-ai-section cdw-ai-keys-section">
                <div className="cdw-section-header">
                    <div className="cdw-section-icon">&#128273;</div>
                    <h2>AI Assistant — API Keys &amp; Model</h2>
                </div>
                <p className="cdw-ai-keys-note">
                    API keys are encrypted and stored per user. They are never exposed in any REST response.
                </p>

                {/* Provider cards */}
                <div className="cdw-provider-cards">
                    {PROVIDERS.map((provider) => (
                        <button
                            key={provider.id}
                            type="button"
                            className={`cdw-provider-card ${selectedProvider === provider.id ? 'active' : ''}`}
                            onClick={() => {
                                setSelectedProvider(provider.id);
                                setSelectedModel('');
                                setTestResult(null);
                            }}
                        >
                            <span className="cdw-provider-icon">{provider.icon}</span>
                            <span className="cdw-provider-label">{provider.label}</span>
                            {aiSettings && aiSettings.provider === provider.id && aiSettings.has_key && (
                                <span className="cdw-provider-badge" title="API key saved">✓</span>
                            )}
                        </button>
                    ))}
                </div>

                <form onSubmit={handleAiSave} className="cdw-ai-key-form">
                    <div className="cdw-field">
                        <label htmlFor="ai_model">Model</label>
                        {currentProviderDef.customModel ? (
                            <input
                                type="text"
                                id="ai_model"
                                value={customModelName}
                                onChange={(e) => setCustomModelName(e.target.value)}
                                placeholder="e.g. llama-3.3-70b-versatile"
                                className="cdw-select"
                            />
                        ) : (
                            <select
                                id="ai_model"
                                value={selectedModel}
                                onChange={(e) => setSelectedModel(e.target.value)}
                                className="cdw-select"
                            >
                                <option value="">— Select model —</option>
                                {currentProviderDef.models.map((m) => (
                                    <option key={m.id} value={m.id}>{m.label}</option>
                                ))}
                            </select>
                        )}
                        <span className="description">{currentProviderDef.description}</span>
                    </div>

                    {currentProviderDef.customUrl && (
                        <div className="cdw-field">
                            <label htmlFor="ai_base_url">Base URL</label>
                            <input
                                type="url"
                                id="ai_base_url"
                                value={baseUrl}
                                onChange={(e) => setBaseUrl(e.target.value)}
                                placeholder="https://api.groq.com/openai/v1"
                            />
                            <span className="description">
                                OpenAI-compatible endpoint. Examples: Groq — <code>https://api.groq.com/openai/v1</code>, OpenRouter — <code>https://openrouter.ai/api/v1</code>
                            </span>
                        </div>
                    )}

                    <div className="cdw-field">
                        <label htmlFor="ai_api_key">
                            API Key
                            {aiSettings && aiSettings.provider === selectedProvider && aiSettings.has_key && (
                                <span className="cdw-key-saved"> (key saved — enter new value to replace)</span>
                            )}
                        </label>
                        <input
                            type="password"
                            id="ai_api_key"
                            value={apiKey}
                            onChange={(e) => setApiKey(e.target.value)}
                            placeholder={
                                aiSettings && aiSettings.provider === selectedProvider && aiSettings.has_key
                                    ? '••••••••••••  (leave blank to keep existing)'
                                    : currentProviderDef.keyPlaceholder
                            }
                            autoComplete="new-password"
                        />
                        <span className="description">
                            Your {currentProviderDef.label} API key. Stored encrypted — never shown after saving.
                        </span>
                    </div>

                    <div className="cdw-ai-key-actions">
                        <button
                            type="submit"
                            className="button button-primary"
                            disabled={aiSaving || aiSettingsLoading}
                        >
                            {aiSaving ? 'Saving…' : 'Save AI Settings'}
                        </button>

                        <button
                            type="button"
                            className="button"
                            onClick={handleTestConnection}
                            disabled={
                                testing ||
                                !(aiSettings && aiSettings.provider === selectedProvider && aiSettings.has_key)
                            }
                            title={
                                !(aiSettings && aiSettings.provider === selectedProvider && aiSettings.has_key)
                                    ? 'Save an API key first'
                                    : 'Test the saved API key'
                            }
                        >
                            {testing ? 'Testing…' : 'Test Connection'}
                        </button>

                        {aiSaved && <span className="cdw-saved-message">Saved!</span>}
                        {aiSaveError && <span className="cdw-error cdw-inline-error">{aiSaveError}</span>}
                        {testResult && (
                            <span className={testResult.ok ? 'cdw-saved-message' : 'cdw-error cdw-inline-error'}>
                                {testResult.ok ? '✓ ' : '✗ '}{testResult.message}
                            </span>
                        )}
                    </div>
                </form>
            </div>

            {/* ================================================================
                AI Usage Dashboard
            ================================================================ */}
            <div className="cdw-settings-section cdw-ai-usage-section">
                <div className="cdw-section-header">
                    <div className="cdw-section-icon">&#128202;</div>
                    <h2>AI Usage</h2>
                </div>

                {usageLoading && <p className="cdw-loading-text">Loading usage stats…</p>}

                {!usageLoading && usage && (
                    <div className="cdw-usage-grid">
                        <div className="cdw-usage-stat">
                            <span className="cdw-usage-value">{(usage.total_requests || 0).toLocaleString()}</span>
                            <span className="cdw-usage-label">Requests</span>
                        </div>
                        <div className="cdw-usage-stat">
                            <span className="cdw-usage-value">{(usage.prompt_tokens || 0).toLocaleString()}</span>
                            <span className="cdw-usage-label">Prompt tokens</span>
                        </div>
                        <div className="cdw-usage-stat">
                            <span className="cdw-usage-value">{(usage.completion_tokens || 0).toLocaleString()}</span>
                            <span className="cdw-usage-label">Completion tokens</span>
                        </div>
                        <div className="cdw-usage-stat cdw-usage-stat--total">
                            <span className="cdw-usage-value">{(usage.total_tokens || 0).toLocaleString()}</span>
                            <span className="cdw-usage-label">Total tokens</span>
                        </div>
                    </div>
                )}

                {!usageLoading && !usage && (
                    <p className="cdw-no-usage">No usage recorded yet. Start a conversation in the AI Assistant widget.</p>
                )}

                <div className="cdw-usage-actions">
                    <button
                        className="button"
                        onClick={resetUsage}
                        disabled={usageResetting}
                    >
                        {usageResetting ? 'Resetting…' : 'Reset Usage Stats'}
                    </button>
                    {usageResetDone && (
                        <span className="cdw-save-success" style={{ marginLeft: '10px' }}>Stats reset.</span>
                    )}
                </div>
                <p className="cdw-usage-note">
                    Usage is tracked per user and reset on plugin uninstall. Token counts are approximate.
                </p>
            </div>
        </div>
    );
}
