import { useState, useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { calculateTimeAgo } from '../utils/timeAgo';

export default function TasksWidget() {
    const tasks = useSelect((select) => select('cdw/store').getTasks());
    const users = useSelect((select) => select('cdw/store').getUsers());
    const isLoading = useSelect((select) => select('cdw/store').isLoading('tasks'));
    const error = useSelect((select) => select('cdw/store').getError('tasks'));
    const { fetchTasks, fetchUsers, addTask, removeTask } = useDispatch('cdw/store');
    const [newTask, setNewTask] = useState('');
    const [assigneeId, setAssigneeId] = useState('');

    useEffect(() => {
        fetchTasks();
        fetchUsers();
    }, []);

    const handleAddTask = async () => {
        if (!newTask.trim()) return;
        const newTaskObj = { name: newTask.trim(), timestamp: Date.now() / 1000 };
        try {
            if (assigneeId) {
                // Assigning to another user: send only the new task so the server
                // can append it to that user's existing list without overwriting it.
                await addTask([newTaskObj], assigneeId);
            } else {
                // Own tasks: send the full updated list; server replaces in full.
                await addTask([...tasks, newTaskObj], null);
            }
            setNewTask('');
            setAssigneeId('');
        } catch (e) {
            console.error('Failed to add task:', e);
        }
    };

    const handleRemoveTask = async (index) => {
        const newTasks = tasks.filter((_, i) => i !== index);
        try {
            // Always save to the current user's list — never to the assignee
            // selected in the "add task" dropdown, which is unrelated to removal.
            await removeTask(newTasks, null);
        } catch (e) {
            console.error('Failed to remove task:', e);
        }
    };

    const handleKeyDown = (e) => {
        if (e.key === 'Enter') {
            handleAddTask();
        }
    };

    if (isLoading && tasks.length === 0) {
        return <div className="cdw-loading">Loading tasks...</div>;
    }

    if (error) {
        return <div className="cdw-error">Failed to load tasks: {error}</div>;
    }

    return (
        <div className="cdw-tasks-widget">
            <table className="cdw-tasks-table">
                <thead>
                    <tr>
                        <th>Task</th>
                        <th>Assigned To</th>
                        <th>Added</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    {tasks.length === 0 ? (
                        <tr>
                            <td colSpan="4" className="cdw-no-tasks">No tasks yet</td>
                        </tr>
                    ) : (
                        tasks.map((task, index) => (
                            <tr key={index}>
                                <td>{task.name}</td>
                                <td className="cdw-task-assignee">
                                    {task.created_by ? (
                                        users.find(u => u.id === task.created_by)?.display_name || 'Unknown'
                                    ) : (
                                        'Me'
                                    )}
                                </td>
                                <td className="cdw-task-time">{calculateTimeAgo(task.timestamp)} ago</td>
                                <td>
                                    <button 
                                        className="cdw-remove-task" 
                                        onClick={() => handleRemoveTask(index)}
                                        aria-label="Remove task"
                                    >
                                        ×
                                    </button>
                                </td>
                            </tr>
                        ))
                    )}
                </tbody>
            </table>
            <div className="cdw-task-input">
                <input
                    type="text"
                    value={newTask}
                    onChange={(e) => setNewTask(e.target.value)}
                    onKeyDown={handleKeyDown}
                    placeholder="Add new task"
                    className="cdw-task-input-field"
                />
                <div className="cdw-task-input-actions">
                    {users.length > 0 && (
                        <select
                            value={assigneeId}
                            onChange={(e) => setAssigneeId(e.target.value)}
                            className="cdw-task-assignee-select"
                        >
                            <option value="">My Tasks</option>
                            {users.map(user => (
                                <option key={user.id} value={user.id}>{user.display_name}</option>
                            ))}
                        </select>
                    )}
                    <button 
                        onClick={handleAddTask}
                        disabled={!newTask.trim()}
                        className="cdw-task-add-btn"
                    >
                        Add
                    </button>
                </div>
            </div>
        </div>
    );
}
