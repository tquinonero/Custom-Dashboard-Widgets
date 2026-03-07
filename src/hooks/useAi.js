import { useState, useEffect, useRef } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { useDispatch } from '@wordpress/data';

export default function useAi(mode) {
    const [aiHistory, setAiHistory] = useState([]);
    const [aiInput, setAiInput] = useState('');
    const [aiLoading, setAiLoading] = useState(false);
    const [aiSettings, setAiSettings] = useState(null);
    const [aiSettingsLoading, setAiSettingsLoading] = useState(false);
    const [showGear, setShowGear] = useState(false);
    const [gearProvider, setGearProvider] = useState('openai');
    const [gearSaving, setGearSaving] = useState(false);
    const [pendingMsg, setPendingMsg] = useState(null);
    const [expandedTools, setExpandedTools] = useState({});
    const aiInputRef = useRef(null);
    const aiOutputRef = useRef(null);
    const gearRef = useRef(null);
    const { fetchTasks } = useDispatch('cdw/store');

    useEffect(() => {
        if (aiOutputRef.current) {
            aiOutputRef.current.scrollTop = aiOutputRef.current.scrollHeight;
        }
    }, [aiHistory, aiLoading]);

    // Load AI settings when switching to AI mode for the first time.
    useEffect(() => {
        if (mode === 'ai' && !aiSettings && !aiSettingsLoading) {
            loadAiSettings();
        }
    }, [mode]); // eslint-disable-line react-hooks/exhaustive-deps

    // Close gear panel on outside click.
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

    const loadAiSettings = async () => {
        setAiSettingsLoading(true);
        try {
            const result = await apiFetch({ path: '/cdw/v1/ai/settings' });
            const settings = result.data || result;
            setAiSettings(settings);
            setGearProvider(settings.provider || 'openai');
        } catch (e) {
            // Non-fatal: widget still usable with defaults.
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
            setAiSettings((prev) => (prev ? { ...prev, provider } : { provider }));
        } catch (e) {
            // Ignore — UI reflects the optimistic change.
        } finally {
            setGearSaving(false);
        }
    };

    const dispatchAiMessage = async (userMessage) => {
        const priorHistory = aiHistory.map((m) => ({ role: m.role, content: m.content }));
        setAiHistory((prev) => [...prev, { role: 'user', content: userMessage }]);
        setAiLoading(true);

        try {
            const result = await apiFetch({
                path: '/cdw/v1/ai/chat',
                method: 'POST',
                data: { message: userMessage, history: priorHistory },
            });
            const payload = result.data || result;
            const toolsMade = Array.isArray(payload.tool_calls_made) ? payload.tool_calls_made : [];
            setAiHistory((prev) => [
                ...prev,
                {
                    role: 'assistant',
                    content: payload.content || '',
                    tool_calls_made: toolsMade,
                },
            ]);
            if (toolsMade.some((tc) => tc.name && tc.name.startsWith('task_'))) {
                fetchTasks();
            }
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

    return {
        aiHistory,
        aiInput, setAiInput,
        aiLoading,
        aiSettings, aiSettingsLoading,
        showGear, setShowGear,
        gearProvider,
        gearSaving,
        pendingMsg,
        expandedTools,
        aiInputRef,
        aiOutputRef,
        gearRef,
        loadAiSettings,
        saveGearProvider,
        sendAiMessage,
        confirmPending,
        cancelPending,
        toggleToolEntry,
        clearAiHistory,
    };
}
