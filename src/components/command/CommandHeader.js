export const PROVIDER_LABELS = {
    openai:    'OpenAI',
    anthropic: 'Anthropic',
    google:    'Google',
    custom:    'Custom',
};

export default function CommandHeader({
    mode,
    switchMode,
    showHelp,
    setShowHelp,
    showGear,
    setShowGear,
    gearRef,
    gearProvider,
    saveGearProvider,
    gearSaving,
    aiSettings,
    onClose,
    isFloating,
}) {
    return (
        <div className="cdw-command-header">
            <div className="cdw-mode-toggle">
                <button
                    className={`cdw-mode-btn ${mode === 'cli' ? 'active' : ''}`}
                    onClick={(e) => { e.stopPropagation(); switchMode('cli'); }}
                    title="CLI mode"
                >
                    &gt;_
                </button>
                <button
                    className={`cdw-mode-btn ${mode === 'ai' ? 'active' : ''}`}
                    onClick={(e) => { e.stopPropagation(); switchMode('ai'); }}
                    title="AI Assistant mode"
                >
                    ✦ AI
                </button>
            </div>

            <span className="cdw-command-title">
                {mode === 'cli' ? 'Command Line' : 'AI Assistant'}
            </span>

            <div className="cdw-header-actions">
                {mode === 'cli' && (
                    <button
                        className="cdw-command-btn"
                        onClick={(e) => { e.stopPropagation(); setShowHelp(!showHelp); }}
                        title="Show help"
                    >
                        ?
                    </button>
                )}
                {mode === 'ai' && (
                    <div className="cdw-gear-wrap" ref={gearRef}>
                        <button
                            className={`cdw-command-btn ${showGear ? 'active' : ''}`}
                            onClick={(e) => { e.stopPropagation(); setShowGear((v) => !v); }}
                            title="AI settings"
                            aria-expanded={showGear}
                        >
                            ⚙
                        </button>
                        {showGear && (
                            <div className="cdw-gear-panel" onClick={(e) => e.stopPropagation()}>
                                <div className="cdw-gear-row">
                                    <span className="cdw-gear-label">Mode</span>
                                    <div className="cdw-mode-toggle cdw-mode-toggle--sm">
                                        <button
                                            className={`cdw-mode-btn ${mode === 'cli' ? 'active' : ''}`}
                                            onClick={() => switchMode('cli')}
                                        >&gt;_ CLI</button>
                                        <button
                                            className={`cdw-mode-btn ${mode === 'ai' ? 'active' : ''}`}
                                            onClick={() => switchMode('ai')}
                                        >✦ AI</button>
                                    </div>
                                </div>
                                <div className="cdw-gear-row">
                                    <span className="cdw-gear-label">Provider</span>
                                    <select
                                        value={gearProvider}
                                        onChange={(e) => saveGearProvider(e.target.value)}
                                        disabled={gearSaving}
                                        className="cdw-gear-select"
                                    >
                                        {Object.entries(PROVIDER_LABELS).map(([id, label]) => (
                                            <option key={id} value={id}>{label}</option>
                                        ))}
                                    </select>
                                </div>
                                {aiSettings && !aiSettings.has_key && (
                                    <div className="cdw-gear-warn">
                                        ⚠ No API key saved
                                    </div>
                                )}
                                <a
                                    href="options-general.php?page=cdw-settings#cdw-ai-settings"
                                    className="cdw-gear-link"
                                    onClick={() => setShowGear(false)}
                                >
                                    Manage API keys →
                                </a>
                            </div>
                        )}
                    </div>
                )}
                {isFloating && onClose && (
                    <button
                        className="cdw-command-btn cdw-close-btn"
                        onClick={(e) => { e.stopPropagation(); onClose(); }}
                        title="Close (Ctrl+Shift+C)"
                    >
                        ✕
                    </button>
                )}
            </div>
        </div>
    );
}
