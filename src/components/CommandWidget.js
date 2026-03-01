import { useState, useEffect, useRef } from '@wordpress/element';

export default function CommandWidget() {
    const [history, setHistory] = useState([]);
    const [command, setCommand] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [showHelp, setShowHelp] = useState(false);
    const inputRef = useRef(null);
    const outputRef = useRef(null);

    useEffect(() => {
        loadHistory();
    }, []);

    useEffect(() => {
        if (outputRef.current) {
            outputRef.current.scrollTop = outputRef.current.scrollHeight;
        }
    }, [history]);

    const loadHistory = async () => {
        try {
            const result = await wp.apiFetch({ 
                path: `/cdw/v1/cli/history?t=${Date.now()}`,
                headers: {
                    'X-WP-Nonce': cdwData.nonce
                }
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

    const executeCommand = async (e) => {
        e.preventDefault();
        if (!command.trim() || isLoading) return;

        const cmd = command.trim();
        setCommand('');
        setIsLoading(true);

        try {
            const result = await wp.apiFetch({
                path: '/cdw/v1/cli/execute',
                method: 'POST',
                data: { command: cmd },
                headers: {
                    'X-WP-Nonce': cdwData.nonce
                }
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
        if (inputRef.current) {
            inputRef.current.focus();
        }
    };

    const handleKeyDown = (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            executeCommand(e);
        }
    };

    const clearHistory = async () => {
        try {
            await wp.apiFetch({
                path: '/cdw/v1/cli/history',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': cdwData.nonce
                }
            });
            setHistory([]);
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
                    onChange={(e) => setCommand(e.target.value)}
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
