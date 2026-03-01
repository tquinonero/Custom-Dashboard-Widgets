import { render } from '@wordpress/element';
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

function DashboardWidgets() {
    return (
        <div className="cdw-dashboard">
            <HelpWidget />
            <StatsWidget />
            <MediaWidget />
            <PostsWidget />
            <TasksWidget />
            <UpdatesWidget />
            <QuickLinksWidget />
            <CommandWidget />
        </div>
    );
}

function SettingsApp() {
    return (
        <div className="cdw-settings">
            <SettingsPanel />
        </div>
    );
}

document.addEventListener('DOMContentLoaded', () => {
    console.log('CDW: DOM loaded');
    
    // Render dashboard widgets
    const dashboardWidgets = document.querySelectorAll('.cdw-widget[data-widget]');
    console.log('CDW: Found dashboard widgets:', dashboardWidgets.length);
    
    dashboardWidgets.forEach(container => {
        const widgetType = container.dataset.widget;
        console.log('CDW: Rendering widget:', widgetType);
        
        switch (widgetType) {
            case 'stats':
                render(<StatsWidget />, container);
                break;
            case 'tasks':
                render(<TasksWidget />, container);
                break;
            case 'posts':
                render(<PostsWidget />, container);
                break;
            case 'media':
                render(<MediaWidget />, container);
                break;
            case 'help':
                render(<HelpWidget />, container);
                break;
            case 'updates':
                render(<UpdatesWidget />, container);
                break;
            case 'quicklinks':
                render(<QuickLinksWidget />, container);
                break;
            case 'command':
                render(<CommandWidget />, container);
                break;
        }
    });

    // Render settings page
    const settingsRoot = document.getElementById('cdw-settings-root');
    console.log('CDW: Settings root found:', !!settingsRoot);
    if (settingsRoot) {
        try {
            render(<SettingsApp />, settingsRoot);
            console.log('CDW: Settings app rendered');
        } catch (e) {
            console.error('CDW: Error rendering settings:', e);
            settingsRoot.innerHTML = '<div class="error"><p>Error loading settings: ' + e.message + '</p></div>';
        }
    }
});
