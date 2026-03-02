import { useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';

export default function UpdatesWidget() {
    const updates = useSelect((select) => select('cdw/store').getUpdates());
    const isLoading = useSelect((select) => select('cdw/store').isLoading('updates'));
    const { fetchUpdates } = useDispatch('cdw/store');

    useEffect(() => {
        fetchUpdates();
    }, []);

    if (isLoading) {
        return <div className="cdw-loading">Loading updates...</div>;
    }

    const pluginUpdates = updates.plugins || [];
    const themeUpdates = updates.themes || [];
    const coreAvailable = updates.core?.available || false;
    const hasUpdates = coreAvailable || pluginUpdates.length > 0 || themeUpdates.length > 0;

    return (
        <div className="cdw-updates-widget">
            {!hasUpdates ? (
                <p>Good job, you have no pending updates.</p>
            ) : (
                <ul className="cdw-updates-list">
                    {coreAvailable && (
                        <li key="core">
                            <strong>WordPress Core</strong> -{' '}
                            <a href={(window.cdwData?.adminUrl || '') + 'update-core.php'}>Update Now</a>
                        </li>
                    )}
                    {pluginUpdates.map((update, index) => (
                        <li key={`plugin-${index}`}>
                            <strong>{update.name}</strong> {update.version} &rarr; {update.new_version} -{' '}
                            <a href={(window.cdwData?.adminUrl || '') + 'plugins.php'}>Update Now</a>
                        </li>
                    ))}
                    {themeUpdates.map((update, index) => (
                        <li key={`theme-${index}`}>
                            <strong>{update.name}</strong> {update.version} &rarr; {update.new_version} -{' '}
                            <a href={(window.cdwData?.adminUrl || '') + 'themes.php'}>Update Now</a>
                        </li>
                    ))}
                </ul>
            )}
            <div className="cdw-updates-links">
                <a href={(window.cdwData?.adminUrl || '') + 'plugins.php'} className="button button-primary">
                    Go to Plugins
                </a>
                <a href={(window.cdwData?.adminUrl || '') + 'plugin-install.php'} className="button">
                    Add New Plugin
                </a>
            </div>
        </div>
    );
}
