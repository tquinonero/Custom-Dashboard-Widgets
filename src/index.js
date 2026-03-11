import { createRoot } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import StatsWidget from './components/StatsWidget';
import TasksWidget from './components/TasksWidget';
import PostsWidget from './components/PostsWidget';
import MediaWidget from './components/MediaWidget';
import HelpWidget from './components/HelpWidget';
import UpdatesWidget from './components/UpdatesWidget';
import QuickLinksWidget from './components/QuickLinksWidget';
import ToolsOtherWidget from './components/ToolsOtherWidget';
import CommandWidget from './components/CommandWidget';
import FloatingCommandWidget from './components/FloatingCommandWidget';
import SettingsPanel from './components/SettingsPanel';
import useFloatingWidget from './hooks/useFloatingWidget';
import './data/store';
import './styles/index.scss';

function SettingsApp() {
    return (
        <div className="cdw-settings">
            <SettingsPanel />
        </div>
    );
}

// Configure nonce for all apiFetch calls globally.
if ( window.cdwData?.nonce ) {
    apiFetch.use( apiFetch.createNonceMiddleware( window.cdwData.nonce ) );
}

document.addEventListener('DOMContentLoaded', () => {
    // ...original code restored, no sidebar or layout manipulation...
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
            case 'toolsother':
                createRoot(container).render(<ToolsOtherWidget />);
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
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error';
            errorDiv.textContent = 'Error loading settings: ' + e.message;
            settingsRoot.appendChild(errorDiv);
        }
    }

    // Render floating command widget (Ctrl+Shift+C)
    if (window.cdwData?.floatingEnabled) {
        const floatingRoot = document.createElement('div');
        floatingRoot.id = 'cdw-floating-root';
        document.body.appendChild(floatingRoot);
        
        const FloatingWrapper = () => {
            const floating = useFloatingWidget('C');
            return <FloatingCommandWidget {...floating} />;
        };
        
        createRoot(floatingRoot).render(<FloatingWrapper />);
    }

});
