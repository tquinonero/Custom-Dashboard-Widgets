import { useState, useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';

export default function SettingsPanel() {
    const settings = useSelect((select) => select('cdw/store').getSettings());
    const isLoading = useSelect((select) => select('cdw/store').isLoading('settings'));
    const { fetchSettings, saveSettings } = useDispatch('cdw/store');

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
    });

    const [saved, setSaved] = useState(false);
    const [saveError, setSaveError] = useState(null);

    useEffect(() => {
        fetchSettings();
    }, []);

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
            });
        }
    }, [settings]);

    const handleChange = (e) => {
        const { name, value, type, checked } = e.target;
        setFormData((prev) => ({ 
            ...prev, 
            [name]: type === 'checkbox' ? checked : value 
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

    return (
        <div className="cdw-settings-panel">
            <div className="cdw-settings-header">
                <h1>Custom Dashboard Widgets</h1>
                <p>Configure your dashboard widgets and appearance settings</p>
            </div>

            <form onSubmit={handleSubmit}>
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
                </div>

                <div className="cdw-settings-actions">
                    <button type="submit" className="button button-primary" disabled={isLoading}>
                        {isLoading ? 'Saving...' : 'Save Changes'}
                    </button>
                    {saved && <span className="cdw-saved-message">Settings saved!</span>}
                    {saveError && <div className="cdw-error">{saveError}</div>}
                </div>
            </form>
        </div>
    );
}
