const QUICK_COMMANDS = [
    { cmd: 'help',        label: 'help' },
    { cmd: 'site status', label: 'status' },
    { cmd: 'plugin list', label: 'plugins' },
    { cmd: 'user list',   label: 'users' },
];

export default function CliPanel({
    history,
    command, setCommand,
    isLoading,
    showHelp, setShowHelp,
    suggestions,
    highlightedIndex, setHighlightedIndex,
    inputRef,
    outputRef,
    updateSuggestions,
    selectSuggestion,
    executeCommand,
    handleKeyDown,
    clearHistory,
    focusInput,
}) {
    return (
        <>
            {/* Quick-help bar */}
            {showHelp && (
                <div className="cdw-command-help">
                    <div className="cdw-help-title">Quick Commands:</div>
                    <div className="cdw-help-commands">
                        {QUICK_COMMANDS.map((q, i) => (
                            <button
                                key={i}
                                className="cdw-help-cmd"
                                onClick={() => {
                                    setCommand(q.cmd);
                                    setShowHelp(false);
                                    focusInput();
                                }}
                            >
                                {q.label}
                            </button>
                        ))}
                    </div>
                </div>
            )}

            {/* Output area */}
            <div className="cdw-command-output" ref={outputRef}>
                {history.length === 0 ? (
                    <div className="cdw-command-welcome">
                        <div className="cdw-welcome-title">ADMIN CLI</div>
                        <div className="cdw-welcome-text">
                            Type <code>help</code> to see available commands
                        </div>
                        <div className="cdw-welcome-examples">
                            <div>Examples:</div>
                            <code>plugin install woocommerce</code>
                            <code>user create john john@example.com author</code>
                            <code>site status</code>
                        </div>
                    </div>
                ) : (
                    history.map((entry, index) => (
                        <div key={index} className={`cdw-command-entry ${entry.success ? 'success' : 'error'}`}>
                            <div className="cdw-entry-prompt">
                                <span className="cdw-prompt-symbol">$</span>
                                <span className="cdw-entry-command">{entry.command}</span>
                            </div>
                            <div className="cdw-entry-output">{entry.output}</div>
                        </div>
                    ))
                )}
                {isLoading && (
                    <div className="cdw-command-loading">
                        <span className="cdw-loading-spinner"></span>
                        Executing...
                    </div>
                )}
            </div>

            {/* Input form */}
            <form className="cdw-command-input" onSubmit={executeCommand}>
                <span className="cdw-input-prompt">$</span>
                <input
                    ref={inputRef}
                    type="text"
                    value={command}
                    onChange={(e) => {
                        const value = e.target.value;
                        setCommand(value);
                        updateSuggestions(value);
                    }}
                    onKeyDown={handleKeyDown}
                    placeholder="Type a command..."
                    disabled={isLoading}
                    className="cdw-command-field"
                />
                <button
                    type="submit"
                    className="cdw-submit-btn"
                    disabled={!command.trim() || isLoading}
                >
                    &#8629;
                </button>
            </form>

            {/* Autocomplete suggestions */}
            {suggestions.length > 0 && (
                <ul className="cdw-command-suggestions" role="listbox">
                    {suggestions.map((suggestion, index) => (
                        <li key={`${suggestion.name}-${index}`}>
                            <button
                                type="button"
                                className={`cdw-command-suggestion ${index === highlightedIndex ? 'is-active' : ''}`}
                                onClick={() => selectSuggestion(suggestion)}
                                onMouseEnter={() => setHighlightedIndex(index)}
                            >
                                <span className="cdw-suggestion-usage">{suggestion.name}</span>
                                <span className="cdw-suggestion-description">{suggestion.description}</span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {/* Footer */}
            {history.length > 0 && (
                <div className="cdw-command-footer">
                    <button className="cdw-clear-btn" onClick={clearHistory}>
                        Clear
                    </button>
                    <span className="cdw-history-count">
                        {history.length} command{history.length !== 1 ? 's' : ''} in history
                    </span>
                </div>
            )}
        </>
    );
}
