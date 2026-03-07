import { useState, useEffect, useRef } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

export default function useCli() {
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
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => {
        if (outputRef.current) {
            outputRef.current.scrollTop = outputRef.current.scrollHeight;
        }
    }, [history]);

    const loadHistory = async () => {
        try {
            const result = await apiFetch({
                path: `/cdw/v1/cli/history?t=${Date.now()}`,
            });
            setHistory(Array.isArray(result) ? result : []);
        } catch (e) {
            console.error('Failed to load history:', e);
            setHistory([]);
        }
    };

    const loadCommandDefinitions = async () => {
        try {
            const result = await apiFetch({
                path: `/cdw/v1/cli/commands?t=${Date.now()}`,
            });
            if (Array.isArray(result)) {
                const flat = result.flatMap((cat) =>
                    Array.isArray(cat.commands) ? cat.commands : []
                );
                setCommandDefinitions(flat);
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
            return (
                name.startsWith(normalized) ||
                name.includes(` ${normalized}`) ||
                name.includes(normalized)
            );
        });
        setSuggestions(matches.slice(0, 6));
        setHighlightedIndex(-1);
    };

    const selectSuggestion = (suggestion) => {
        if (!suggestion) return;
        const normalized = suggestion.name.endsWith(' ')
            ? suggestion.name
            : `${suggestion.name} `;
        setCommand(normalized);
        updateSuggestions(normalized);
        setSuggestions([]);
        setHighlightedIndex(-1);
        if (inputRef.current) inputRef.current.focus();
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
                data: { command: cmd },
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

            setHistory((prev) => [
                { command: cmd, output, success, timestamp: Date.now() },
                ...prev,
            ]);
        } catch (e) {
            setHistory((prev) => [
                {
                    command: cmd,
                    output: 'Error: ' + (e.message || e.code || 'Request failed'),
                    success: false,
                    timestamp: Date.now(),
                },
                ...prev,
            ]);
        }

        setIsLoading(false);
        setSuggestions([]);
        setHighlightedIndex(-1);
        if (inputRef.current) inputRef.current.focus();
    };

    const handleKeyDown = (e) => {
        if (suggestions.length > 0 && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
            e.preventDefault();
            setHighlightedIndex((prev) => {
                if (prev === -1) return e.key === 'ArrowDown' ? 0 : suggestions.length - 1;
                const next = prev + (e.key === 'ArrowDown' ? 1 : -1);
                return (next + suggestions.length) % suggestions.length;
            });
            return;
        }
        if (suggestions.length > 0 && e.key === 'Tab') {
            e.preventDefault();
            selectSuggestion(suggestions[highlightedIndex >= 0 ? highlightedIndex : 0]);
            return;
        }
        if (e.key === 'Enter' && !e.shiftKey) {
            executeCommand(e);
        }
    };

    const clearHistory = async () => {
        try {
            await apiFetch({ path: '/cdw/v1/cli/history', method: 'DELETE' });
            setHistory([]);
            setSuggestions([]);
            setHighlightedIndex(-1);
        } catch (e) {
            console.error('Failed to clear history:', e);
            setHistory([]);
        }
    };

    const focusInput = () => {
        if (inputRef.current) inputRef.current.focus();
    };

    return {
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
    };
}
