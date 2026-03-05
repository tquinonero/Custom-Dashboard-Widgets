import { useState, useEffect, useRef } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

// ---------------------------------------------------------------------------
// Provider catalogue (mirrors backend CDW_AI_Service::get_providers)
// ---------------------------------------------------------------------------
const PROVIDER_LABELS = {
    openai:    'OpenAI',
    anthropic: 'Anthropic',
    google:    'Google',
    custom:    'Custom',
};

// ---------------------------------------------------------------------------
// CommandWidget
// ---------------------------------------------------------------------------
export default function CommandWidget() {
    // -----------------------------------------------------------------------
    // Mode: 'cli' | 'ai'
    // -----------------------------------------------------------------------
    const [mode, setMode] = useState(() => {
        try { return localStorage.getItem('cdw_mode') || 'cli'; }
        catch (e) { return 'cli'; }
    });

    // -----------------------------------------------------------------------
    // CLI state
    // -----------------------------------------------------------------------
    const [history, setHistory] = useState([]);
    const [command, setCommand] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [showHelp, setShowHelp] = useState(false);
    const [commandDefinitions, setCommandDefinitions] = useState([]);
    const [suggestions, setSuggestions] = useState([]);
    const [highlightedIndex, setHighlightedIndex] = useState(-1);
    const inputRef = useRef(null);
    const outputRef = useRef(null);

    // -----------------------------------------------------------------------
    // AI state
    // -----------------------------------------------------------------------
    const [aiHistory, setAiHistory] = useState([]);
    const [aiInput, setAiInput] = useState('');
    const [aiLoading, setAiLoading] = useState(false);
    const [aiSettings, setAiSettings] = useState(null);
    const [aiSettingsLoading, setAiSettingsLoading] = useState(false);

    // Gear panel
    const [showGear, setShowGear] = useState(false);
    const [gearProvider, setGearProvider] = useState('openai');
    const [gearSaving, setGearSaving] = useState(false);

    // Confirm-first mode: pending message before dispatch
    const [pendingMsg, setPendingMsg] = useState(null);

    // Collapsible tool-call sub-entries: { [msgIndex_toolIndex]: bool }
    const [expandedTools, setExpandedTools] = useState({});

    const aiInputRef = useRef(null);
    const aiOutputRef = useRef(null);
    const gearRef = useRef(null);

    // -----------------------------------------------------------------------
    // Effects
    // -----------------------------------------------------------------------
    useEffect(() => {
        loadHistory();
        loadCommandDefinitions();
    }, []);

    useEffect(() => {
        if (outputRef.current) {
            outputRef.current.scrollTop = outputRef.current.scrollHeight;
        }
    }, [history]);

    useEffect(() => {
        if (aiOutputRef.current) {
            aiOutputRef.current.scrollTop = aiOutputRef.current.scrollHeight;
        }
    }, [aiHistory, aiLoading]);

    // Load AI settings when switching to AI mode
    useEffect(() => {
        if (mode === 'ai' && !aiSettings && !aiSettingsLoading) {
            loadAiSettings();
        }
    }, [mode]); // eslint-disable-line react-hooks/exhaustive-deps

    // Close gear panel on outside click
    useEffect(() => {
        if (!showGear) return;
        const handleClick = (e) => {
            if (gearRef.current && !gearRef.current.contains(e.target)) {
                setShowGear(false);
            }
        };
        document.addEventListener('mousedown', handleClick);
        return () => document.removeEventListener('mousedown', handleClick);
    }, [showGear]);

    // -----------------------------------------------------------------------
    // Mode helpers
    // -----------------------------------------------------------------------
    const switchMode = (newMode) => {
        setMode(newMode);
        try { localStorage.setItem('cdw_mode', newMode); } catch (e) { /* ignore */ }
        setShowGear(false);
        if (newMode === 'ai' && !aiSettings && !aiSettingsLoading) {
            loadAiSettings();
        }
    };

    // -----------------------------------------------------------------------
    // AI helpers
    // -----------------------------------------------------------------------
    const loadAiSettings = async () => {
        setAiSettingsLoading(true);
        try {
            const result = await apiFetch({ path: '/cdw/v1/ai/settings' });
            const settings = result.data || result;
            setAiSettings(settings);
            setGearProvider(settings.provider || 'openai');
        } catch (e) {
            // Non-fatal: widget still usable, settings will show defaults
        } finally {
            setAiSettingsLoading(false);
        }
    };

    const saveGearProvider = async (provider) => {
        setGearProvider(provider);
        setGearSaving(true);
        try {
            await apiFetch({
                path: '/cdw/v1/ai/settings',
                method: 'POST',
                data: { provider },
            });
            setAiSettings((prev) => prev ? { ...prev, provider } : { provider });
        } catch (e) {
            // Ignore — UI already reflects the optimistic change
        } finally {
            setGearSaving(false);
        }
    };

    const dispatchAiMessage = async (userMessage) => {
        // Capture history snapshot before state updates
        const priorHistory = aiHistory.map((m) => ({ role: m.role, content: m.content }));

        const userEntry = { role: 'user', content: userMessage };
        setAiHistory((prev) => [...prev, userEntry]);
        setAiLoading(true);

        try {
            const result = await apiFetch({
                path: '/cdw/v1/ai/chat',
                method: 'POST',
                data: { message: userMessage, history: priorHistory },
            });
            const payload = result.data || result;
            const assistantEntry = {
                role: 'assistant',
                content: payload.content || '',
                tool_calls_made: Array.isArray(payload.tool_calls_made)
                    ? payload.tool_calls_made
                    : [],
            };
            setAiHistory((prev) => [...prev, assistantEntry]);
        } catch (e) {
            const errMsg =
                (e.data && e.data.message) || e.message || e.code || 'Request failed';
            setAiHistory((prev) => [
                ...prev,
                { role: 'assistant', content: '', error: errMsg, tool_calls_made: [] },
            ]);
        } finally {
            setAiLoading(false);
        }
    };

    const sendAiMessage = (e) => {
        e.preventDefault();
        const msg = aiInput.trim();
        if (!msg || aiLoading) return;
        setAiInput('');

        const executionMode = aiSettings ? aiSettings.execution_mode : 'confirm';

        if (executionMode === 'confirm') {
            // Show confirmation banner before dispatching
            setPendingMsg(msg);
        } else {
            dispatchAiMessage(msg);
        }
    };

    const confirmPending = () => {
        if (!pendingMsg) return;
        const msg = pendingMsg;
        setPendingMsg(null);
        dispatchAiMessage(msg);
    };

    const cancelPending = () => {
        setPendingMsg(null);
        setAiInput(pendingMsg || '');
    };

    const toggleToolEntry = (key) => {
        setExpandedTools((prev) => ({ ...prev, [key]: !prev[key] }));
    };

    const clearAiHistory = () => {
        setAiHistory([]);
        setExpandedTools({});
    };

    // -----------------------------------------------------------------------
    // CLI helpers
    // -----------------------------------------------------------------------

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
        <div className={`cdw-command-widget ${mode === 'ai' ? 'cdw-ai-mode' : ''}`}
             onClick={mode === 'cli' ? focusInput : undefined}>

            {/* ----------------------------------------------------------------
                Header
            ---------------------------------------------------------------- */}
            <div className="cdw-command-header">
                {/* Mode toggle */}
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
                </div>
            </div>

            {/* ----------------------------------------------------------------
                CLI mode quick-help
            ---------------------------------------------------------------- */}
            {mode === 'cli' && showHelp && (
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

            {/* ================================================================
                CLI MODE OUTPUT
            ================================================================ */}
            {mode === 'cli' && (
                <>
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
                </>
            )}

            {/* ================================================================
                AI MODE OUTPUT
            ================================================================ */}
            {mode === 'ai' && (
                <>
                    {/* No API key notice */}
                    {aiSettings && !aiSettings.has_key && (
                        <div className="cdw-ai-notice">
                            <span>⚠ No API key for <strong>{PROVIDER_LABELS[aiSettings.provider] || aiSettings.provider}</strong>.</span>
                            <a href="options-general.php?page=cdw-settings#cdw-ai-settings">
                                Add key in Settings →
                            </a>
                        </div>
                    )}

                    {/* Chat bubbles */}
                    <div className="cdw-ai-output" ref={aiOutputRef}>
                        {aiHistory.length === 0 && !aiLoading && (
                            <div className="cdw-ai-welcome">
                                <div className="cdw-ai-welcome-icon">✦</div>
                                <div className="cdw-ai-welcome-title">AI Assistant</div>
                                <div className="cdw-ai-welcome-text">
                                    Ask me anything about your WordPress site.
                                    I can manage plugins, themes, users, posts, and more.
                                </div>
                                <div className="cdw-ai-welcome-examples">
                                    <button onClick={() => setAiInput('List all active plugins')}>
                                        List all active plugins
                                    </button>
                                    <button onClick={() => setAiInput('Show site settings')}>
                                        Show site settings
                                    </button>
                                    <button onClick={() => setAiInput('Install and activate woocommerce')}>
                                        Install WooCommerce
                                    </button>
                                </div>
                            </div>
                        )}

                        {aiHistory.map((msg, msgIdx) => (
                            <div
                                key={msgIdx}
                                className={`cdw-ai-bubble cdw-ai-bubble--${msg.role}${msg.error ? ' cdw-ai-bubble--error' : ''}`}
                            >
                                <div className="cdw-ai-bubble-role">
                                    {msg.role === 'user' ? 'You' : 'AI'}
                                </div>
                                <div className="cdw-ai-bubble-content">
                                    {msg.error
                                        ? <span className="cdw-ai-error">⚠ {msg.error}</span>
                                        : msg.content}
                                </div>

                                {/* Tool call sub-entries */}
                                {Array.isArray(msg.tool_calls_made) && msg.tool_calls_made.length > 0 && (
                                    <div className="cdw-ai-tools">
                                        {msg.tool_calls_made.map((tc, tcIdx) => {
                                            const key = `${msgIdx}_${tcIdx}`;
                                            const isExpanded = !!expandedTools[key];
                                            return (
                                                <div key={key} className="cdw-ai-tool-entry">
                                                    <button
                                                        className="cdw-ai-tool-toggle"
                                                        onClick={() => toggleToolEntry(key)}
                                                        title={isExpanded ? 'Collapse' : 'Expand'}
                                                    >
                                                        <span className="cdw-ai-tool-arrow">
                                                            {isExpanded ? '▾' : '▸'}
                                                        </span>
                                                        <span className="cdw-ai-tool-name">
                                                            ↳ ran: {tc.name}
                                                        </span>
                                                    </button>
                                                    {isExpanded && (
                                                        <div className="cdw-ai-tool-output">
                                                            {typeof tc.output === 'string'
                                                                ? tc.output
                                                                : JSON.stringify(tc.output, null, 2)}
                                                        </div>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                            </div>
                        ))}

                        {aiLoading && (
                            <div className="cdw-ai-bubble cdw-ai-bubble--assistant cdw-ai-thinking">
                                <div className="cdw-ai-bubble-role">AI</div>
                                <div className="cdw-ai-dots">
                                    <span></span><span></span><span></span>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Confirmation banner (confirm-first mode) */}
                    {pendingMsg && (
                        <div className="cdw-ai-confirm">
                            <div className="cdw-ai-confirm-text">
                                <strong>Send to AI:</strong> <em>{pendingMsg}</em>
                            </div>
                            <div className="cdw-ai-confirm-note">
                                The AI may execute WordPress commands to fulfil this request.
                            </div>
                            <div className="cdw-ai-confirm-actions">
                                <button className="cdw-ai-confirm-yes" onClick={confirmPending}>
                                    Proceed
                                </button>
                                <button className="cdw-ai-confirm-no" onClick={cancelPending}>
                                    Cancel
                                </button>
                            </div>
                        </div>
                    )}

                    {/* AI text input */}
                    <form
                        className="cdw-ai-input-bar"
                        onSubmit={sendAiMessage}
                        onClick={(e) => e.stopPropagation()}
                    >
                        <input
                            ref={aiInputRef}
                            type="text"
                            value={aiInput}
                            onChange={(e) => setAiInput(e.target.value)}
                            placeholder={
                                aiSettings && !aiSettings.has_key
                                    ? 'Add an API key first…'
                                    : 'Ask the AI assistant…'
                            }
                            disabled={aiLoading || !!pendingMsg}
                            className="cdw-ai-field"
                        />
                        <button
                            type="submit"
                            className="cdw-ai-send-btn"
                            disabled={!aiInput.trim() || aiLoading || !!pendingMsg}
                            title="Send"
                        >
                            &#8629;
                        </button>
                    </form>

                    {aiHistory.length > 0 && (
                        <div className="cdw-command-footer">
                            <button className="cdw-clear-btn" onClick={clearAiHistory}>
                                Clear
                            </button>
                            <span className="cdw-history-count">
                                {aiHistory.filter((m) => m.role === 'user').length} message
                                {aiHistory.filter((m) => m.role === 'user').length !== 1 ? 's' : ''}
                            </span>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}
