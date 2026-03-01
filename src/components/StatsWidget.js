import { useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js';
import { Doughnut } from 'react-chartjs-2';

ChartJS.register(ArcElement, Tooltip, Legend);

export default function StatsWidget() {
    const stats = useSelect((select) => select('cdw/store').getStats());
    const isLoading = useSelect((select) => select('cdw/store').isLoading('stats'));
    const { fetchStats } = useDispatch('cdw/store');

    useEffect(() => {
        fetchStats();
    }, []);

    const labels = ['Posts', 'Pages', 'Comments', 'Users', 'Media', 'Categories', 'Tags'];
    const dataValues = [stats.posts, stats.pages, stats.comments, stats.users, stats.media, stats.categories, stats.tags];
    const colors = [
        ['#2271b1', '#1a4f8a'],
        ['#00a32a', '#007a2f'],
        ['#d63638', '#a82828'],
        ['#8f5bff', '#6d3fd4'],
        ['#f7d794', '#d4b87a'],
        ['#f0c33c', '#b88c12'],
        ['#72aee6', '#4a9ccc'],
    ];

    if (stats.products !== undefined) {
        labels.push('Products');
        dataValues.push(stats.products);
        colors.push(['#96588a', '#6f3c5f']);
    }

    const chartData = {
        labels: labels,
        datasets: [
            {
                data: dataValues,
                backgroundColor: colors.map(c => c[0]),
                borderColor: colors.map(c => c[1]),
                borderWidth: 2,
            },
        ],
    };

    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 15,
                    usePointStyle: true,
                },
            },
        },
        cutout: '60%',
    };

    if (isLoading) {
        return <div className="cdw-loading">Loading stats...</div>;
    }

    return (
        <div className="cdw-stats-widget">
            <div className="cdw-stats-chart">
                <Doughnut data={chartData} options={chartOptions} />
            </div>
            <div className="cdw-stats-list">
                <div className="cdw-stat-item">
                    <span className="cdw-stat-label">Posts</span>
                    <span className="cdw-stat-value">{stats.posts}</span>
                </div>
                <div className="cdw-stat-item">
                    <span className="cdw-stat-label">Pages</span>
                    <span className="cdw-stat-value">{stats.pages}</span>
                </div>
                <div className="cdw-stat-item">
                    <span className="cdw-stat-label">Comments</span>
                    <span className="cdw-stat-value">{stats.comments}</span>
                </div>
                <div className="cdw-stat-item">
                    <span className="cdw-stat-label">Users</span>
                    <span className="cdw-stat-value">{stats.users}</span>
                </div>
                <div className="cdw-stat-item">
                    <span className="cdw-stat-label">Media</span>
                    <span className="cdw-stat-value">{stats.media}</span>
                </div>
                <div className="cdw-stat-item">
                    <span className="cdw-stat-label">Categories</span>
                    <span className="cdw-stat-value">{stats.categories}</span>
                </div>
                <div className="cdw-stat-item">
                    <span className="cdw-stat-label">Tags</span>
                    <span className="cdw-stat-value">{stats.tags}</span>
                </div>
                {stats.products !== undefined && (
                    <div className="cdw-stat-item">
                        <span className="cdw-stat-label">Products</span>
                        <span className="cdw-stat-value">{stats.products}</span>
                    </div>
                )}
            </div>
        </div>
    );
}
