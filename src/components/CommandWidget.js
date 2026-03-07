import { useState } from '@wordpress/element';
import useCli from '../hooks/useCli';
import useAi from '../hooks/useAi';
import CommandHeader from './command/CommandHeader';
import CliPanel from './command/CliPanel';
import AiPanel from './command/AiPanel';

export default function CommandWidget() {
    const [mode, setMode] = useState(() => {
        try { return localStorage.getItem('cdw_mode') || 'cli'; }
        catch (e) { return 'cli'; }
    });

    const cli = useCli();
    const ai  = useAi(mode);

    const switchMode = (newMode) => {
        setMode(newMode);
        try { localStorage.setItem('cdw_mode', newMode); } catch (e) { /* ignore */ }
        ai.setShowGear(false);
    };

    return (
        <div
            className={`cdw-command-widget ${mode === 'ai' ? 'cdw-ai-mode' : ''}`}
            onClick={mode === 'cli' ? cli.focusInput : undefined}
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
            />
            {mode === 'cli' && <CliPanel {...cli} />}
            {mode === 'ai'  && <AiPanel  {...ai}  />}
        </div>
    );
}
