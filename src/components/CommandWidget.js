import { useState, useEffect, useRef } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

export default function CommandWidget() {
    const [history, setHistory] = useState([]);
    const [command, setCommand] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [showHelp, setShowHelp] = useState(false);
    const [commandDefinitions, setCommandDefinitions] = useState([]);
    const [suggestions, setSuggestions] = useState([]);
    const [highlightedIndex, setHighlightedIndex] = useState(-1);
    const inputRef = useRef(null);
    const outputRef = useRef(null);

    useEffect(() => {
        loadHistory();
        loadCommandDefinitions();
    }, []);

    useEffect(() => {
        if (outputRef.current) {
            outputRef.current.scrollTop = outputRef.current.scrollHeight;
        }
    }, [history]);

    const loadHistory = async () => {
        try {
            const result = await apiFetch({ 
                path: `/cdw/v1/cli/history?t=${Date.now()}`
            });
            if (Array.isArray(result)) {
                setHistory(result);
            } else {
                setHistory([]);
            }
        } catch (e) {
            console.error('Failed to load history:', e);
            setHistory([]);
        }
    };

    const loadCommandDefinitions = async () => {
        try {
            const result = await apiFetch({
                path: `/cdw/v1/cli/commands?t=${Date.now()}`
            });

            if (Array.isArray(result)) {
                // result is an array of category objects: { category, commands[] }
                // flatten to a single array of { name, description } for autocomplete
                const flatCommands = result.flatMap(cat => Array.isArray(cat.commands) ? cat.commands : []);
                setCommandDefinitions(flatCommands);
                if (command.trim()) {
                    updateSuggestions(command, flatCommands);
                }
            }
        } catch (e) {
            console.error('Failed to load CLI commands:', e);
        }
    };

    const updateSuggestions = (value, availableCommands = commandDefinitions) => {
        const normalized = value.trim().toLowerCase();

        if (!normalized || availableCommands.length === 0) {
            setSuggestions([]);
            setHighlightedIndex(-1);
            return;
        }

        const matches = availableCommands.filter((cmd) => {
            const name = cmd.name.toLowerCase();
            return name.startsWith(normalized) || name.includes(` ${normalized}`) || name.includes(normalized);
        });

        setSuggestions(matches.slice(0, 6));
        setHighlightedIndex(-1);
    };

    const selectSuggestion = (suggestion) => {
        if (!suggestion) {
            return;
        }

        const normalized = suggestion.name.endsWith(' ') ? suggestion.name : `${suggestion.name} `;
        setCommand(normalized);
        updateSuggestions(normalized);
        setSuggestions([]);
        setHighlightedIndex(-1);

        if (inputRef.current) {
            inputRef.current.focus();
        }
    };

    const executeCommand = async (e) => {
        e.preventDefault();
        if (!command.trim() || isLoading) return;

        const cmd = command.trim();
        setCommand('');
        setIsLoading(true);

        try {
            const result = await apiFetch({
                path: '/cdw/v1/cli/execute',
                method: 'POST',
                data: { command: cmd }
            });

            let output = '';
            let success = true;

            if (result && result.output !== undefined) {
                output = result.output;
                success = result.success;
            } else if (result && result.message) {
                output = result.message;
                success = false;
            } else if (result && result.code) {
                output = result.code + ': ' + (result.message || 'Unknown error');
                success = false;
            } else if (result && typeof result === 'string') {
                output = result;
            } else {
                output = 'Unknown response format';
                success = false;
            }

            const newEntry = {
                command: cmd,
                output: output,
                success: success,
                timestamp: Date.now()
            };

            setHistory(prev => [newEntry, ...prev]);
        } catch (e) {
            const newEntry = {
                command: cmd,
                output: 'Error: ' + (e.message || e.code || 'Request failed'),
                success: false,
                timestamp: Date.now()
            };
            setHistory(prev => [newEntry, ...prev]);
        }

        setIsLoading(false);
        setSuggestions([]);
        setHighlightedIndex(-1);
        if (inputRef.current) {
            inputRef.current.focus();
        }
    };

    const handleKeyDown = (e) => {
        if (suggestions.length > 0 && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
            e.preventDefault();
            setHighlightedIndex((prev) => {
                if (prev === -1) {
                    return e.key === 'ArrowDown' ? 0 : suggestions.length - 1;
                }
                const next = prev + (e.key === 'ArrowDown' ? 1 : -1);
                return (next + suggestions.length) % suggestions.length;
            });
            return;
        }

        if (suggestions.length > 0 && e.key === 'Tab') {
            e.preventDefault();
            const index = highlightedIndex >= 0 ? highlightedIndex : 0;
            selectSuggestion(suggestions[index]);
            return;
        }

        if (e.key === 'Enter' && !e.shiftKey) {
            executeCommand(e);
        }
    };

    const clearHistory = async () => {
        try {
            await apiFetch({
                path: '/cdw/v1/cli/history',
                method: 'DELETE'
            });
            setHistory([]);
            setSuggestions([]);
            setHighlightedIndex(-1);
        } catch (e) {
            console.error('Failed to clear history:', e);
            setHistory([]);
        }
    };

    const focusInput = () => {
        if (inputRef.current) {
            inputRef.current.focus();
        }
    };

    const quickCommands = [
        { cmd: 'help', label: 'help' },
        { cmd: 'site status', label: 'status' },
        { cmd: 'plugin list', label: 'plugins' },
        { cmd: 'user list', label: 'users' },
    ];

    return (
        <div className="cdw-command-widget" onClick={focusInput}>
            <div className="cdw-command-header">
                <span className="cdw-command-title">Command Line</span>
                <button 
                    className="cdw-command-btn" 
                    onClick={() => setShowHelp(!showHelp)}
                    title="Show help"
                >
                    ?
                </button>
            </div>

            {showHelp && (
                <div className="cdw-command-help">
                    <div className="cdw-help-title">Quick Commands:</div>
                    <div className="cdw-help-commands">
                        {quickCommands.map((q, i) => (
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
        </div>
    );
}
