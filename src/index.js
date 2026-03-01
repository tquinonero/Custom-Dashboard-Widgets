import { createRoot } from '@wordpress/element';
import StatsWidget from './components/StatsWidget';
import TasksWidget from './components/TasksWidget';
import PostsWidget from './components/PostsWidget';
import MediaWidget from './components/MediaWidget';
import HelpWidget from './components/HelpWidget';
import UpdatesWidget from './components/UpdatesWidget';
import QuickLinksWidget from './components/QuickLinksWidget';
import CommandWidget from './components/CommandWidget';
import SettingsPanel from './components/SettingsPanel';
import './data/store';
import './styles/index.scss';

function SettingsApp() {
    return (
        <div className="cdw-settings">
            <SettingsPanel />
        </div>
    );
}

document.addEventListener('DOMContentLoaded', () => {
    // Render dashboard widgets
    const dashboardWidgets = document.querySelectorAll('.cdw-widget[data-widget]');
    
    dashboardWidgets.forEach(container => {
        const widgetType = container.dataset.widget;
        
        switch (widgetType) {
            case 'stats':
                createRoot(container).render(<StatsWidget />);
                break;
            case 'tasks':
                createRoot(container).render(<TasksWidget />);
                break;
            case 'posts':
                createRoot(container).render(<PostsWidget />);
                break;
            case 'media':
                createRoot(container).render(<MediaWidget />);
                break;
            case 'help':
                createRoot(container).render(<HelpWidget />);
                break;
            case 'updates':
                createRoot(container).render(<UpdatesWidget />);
                break;
            case 'quicklinks':
                createRoot(container).render(<QuickLinksWidget />);
                break;
            case 'command':
                createRoot(container).render(<CommandWidget />);
                break;
        }
    });

    // Render settings page
    const settingsRoot = document.getElementById('cdw-settings-root');
    if (settingsRoot) {
        try {
            createRoot(settingsRoot).render(<SettingsApp />);
        } catch (e) {
            console.error('CDW: Error rendering settings:', e);
            settingsRoot.innerHTML = '<div class="error"><p>Error loading settings: ' + e.message + '</p></div>';
        }
    }
});
