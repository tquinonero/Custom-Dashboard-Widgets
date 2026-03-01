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

    return (
        <div className="cdw-updates-widget">
            {updates.length === 0 ? (
                <p>Good job, you have no pending updates.</p>
            ) : (
                <ul className="cdw-updates-list">
                    {updates.map((update, index) => (
                        <li key={index}>
                            <strong>{update.name}</strong> -{' '}
                            <a href={(window.cdwData?.adminUrl || '') + 'update-core.php'}>Update Now</a>
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
