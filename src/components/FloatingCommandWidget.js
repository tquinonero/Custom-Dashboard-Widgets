import { useState } from '@wordpress/element';
import useCli from '../hooks/useCli';
import useAi from '../hooks/useAi';
import CommandHeader from './command/CommandHeader';
import CliPanel from './command/CliPanel';
import AiPanel from './command/AiPanel';

export default function FloatingCommandWidget({
    isVisible,
    position,
    isDragging,
    widgetRef,
    toggle,
    close,
    handleMouseDown,
}) {
    const [mode, setMode] = useState(() => {
        try { return localStorage.getItem('cdw_mode') || 'cli'; }
        catch (e) { return 'cli'; }
    });

    const cli = useCli();
    const ai = useAi(mode);

    const switchMode = (newMode) => {
        setMode(newMode);
        try { localStorage.setItem('cdw_mode', newMode); } catch (e) { /* ignore */ }
        ai.setShowGear(false);
    };

    if (!isVisible) {
        return (
            <button
                className="cdw-floating-toggle"
                onClick={toggle}
                title="Open CLI (Ctrl+Shift+C)"
            >
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <polyline points="4 17 10 11 4 5"></polyline>
                    <line x1="12" y1="19" x2="20" y2="19"></line>
                </svg>
            </button>
        );
    }

    return (
        <div
            ref={widgetRef}
            className={`cdw-command-widget cdw-floating-widget ${mode === 'ai' ? 'cdw-ai-mode' : ''} ${isDragging ? 'is-dragging' : ''}`}
            style={{
                left: position.x,
                top: position.y,
            }}
            onMouseDown={handleMouseDown}
        >
            <CommandHeader
                mode={mode}
                switchMode={switchMode}
                showHelp={cli.showHelp}
                setShowHelp={cli.setShowHelp}
                showGear={ai.showGear}
                setShowGear={ai.setShowGear}
                gearRef={ai.gearRef}
                gearProvider={ai.gearProvider}
                saveGearProvider={ai.saveGearProvider}
                gearSaving={ai.gearSaving}
                aiSettings={ai.aiSettings}
                onClose={close}
                isFloating={true}
            />
            <div className="cdw-floating-body" onClick={(e) => e.stopPropagation()}>
                {mode === 'cli' && <CliPanel {...cli} />}
                {mode === 'ai' && <AiPanel {...ai} />}
            </div>
        </div>
    );
}
