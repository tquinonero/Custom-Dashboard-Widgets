import { PROVIDER_LABELS } from './CommandHeader';

export default function AiPanel({
    aiHistory,
    aiInput, setAiInput,
    aiLoading,
    aiSettings,
    pendingMsg,
    expandedTools,
    aiInputRef,
    aiOutputRef,
    sendAiMessage,
    confirmPending,
    cancelPending,
    toggleToolEntry,
    clearAiHistory,
}) {
    return (
        <>
            {/* No API key notice */}
            {aiSettings && !aiSettings.has_key && (
                <div className="cdw-ai-notice">
                    <span>
                        ⚠ No API key for{' '}
                        <strong>{PROVIDER_LABELS[aiSettings.provider] || aiSettings.provider}</strong>.
                    </span>
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

            {/* Footer */}
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
    );
}
