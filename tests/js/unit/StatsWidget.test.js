/**
 * Tests for StatsWidget.js component.
 *
 * Chart.js requires a Canvas environment; both chart.js and react-chartjs-2
 * are mocked so the DOM-only jsdom environment stays happy.
 */
import { render, screen } from '@testing-library/react';
import { useSelect, useDispatch } from '@wordpress/data';

// Mock Chart.js and react-chartjs-2 before importing the component.
jest.mock('chart.js', () => ({
    Chart:      { register: jest.fn() },
    ArcElement: {},
    Tooltip:    {},
    Legend:     {},
}));

jest.mock('react-chartjs-2', () => ({
    Doughnut: ({ data }) => (
        <div
            data-testid="doughnut-chart"
            data-labels={JSON.stringify(data?.labels ?? [])}
        />
    ),
}));

import StatsWidget from '../../../src/components/StatsWidget';

function mockStore({ stats = {}, isLoading = false } = {}) {
    const DEFAULT = {
        posts: 0, pages: 0, comments: 0, users: 0,
        media: 0, categories: 0, tags: 0,
    };
    const merged = { ...DEFAULT, ...stats };

    useSelect.mockImplementation((fn) =>
        fn((/*storeName*/) => ({
            getStats:  () => merged,
            isLoading: () => isLoading,
        }))
    );
    useDispatch.mockReturnValue({ fetchStats: jest.fn() });
}

beforeEach(() => mockStore());
afterEach(() => jest.clearAllMocks());

// ── Loading state ─────────────────────────────────────────────────────────────
describe('StatsWidget — loading', () => {
    it('shows loading spinner when isLoading=true', () => {
        mockStore({ isLoading: true });
        render(<StatsWidget />);

        expect(screen.getByText(/loading stats/i)).toBeInTheDocument();
    });

    it('does not render the chart while loading', () => {
        mockStore({ isLoading: true });
        render(<StatsWidget />);

        expect(screen.queryByTestId('doughnut-chart')).not.toBeInTheDocument();
    });

    it('does not show the loading spinner when data is available', () => {
        mockStore({ stats: { posts: 5 }, isLoading: false });
        render(<StatsWidget />);

        expect(screen.queryByText(/loading stats/i)).not.toBeInTheDocument();
    });
});

// ── Stat values ───────────────────────────────────────────────────────────────
describe('StatsWidget — stat labels and values', () => {
    const stats = {
        posts: 10, pages: 3, comments: 25, users: 7,
        media: 50, categories: 4, tags: 12,
    };

    beforeEach(() => mockStore({ stats }));

    it('shows Posts label', () => {
        render(<StatsWidget />);
        expect(screen.getByText('Posts')).toBeInTheDocument();
    });

    it('shows the post count value', () => {
        render(<StatsWidget />);
        expect(screen.getByText('10')).toBeInTheDocument();
    });

    it('shows Comments label', () => {
        render(<StatsWidget />);
        expect(screen.getByText('Comments')).toBeInTheDocument();
    });

    it('shows Users label', () => {
        render(<StatsWidget />);
        expect(screen.getByText('Users')).toBeInTheDocument();
    });

    it('shows Tags label', () => {
        render(<StatsWidget />);
        expect(screen.getByText('Tags')).toBeInTheDocument();
    });
});

// ── Chart rendering ───────────────────────────────────────────────────────────
describe('StatsWidget — chart', () => {
    it('renders the Doughnut chart when not loading', () => {
        mockStore({ stats: { posts: 5 }, isLoading: false });
        render(<StatsWidget />);

        expect(screen.getByTestId('doughnut-chart')).toBeInTheDocument();
    });

    it('chart data-labels includes standard stat labels', () => {
        mockStore({ stats: { posts: 5, pages: 2, comments: 8, users: 3, media: 10, categories: 4, tags: 6 }, isLoading: false });
        render(<StatsWidget />);

        const chart  = screen.getByTestId('doughnut-chart');
        const labels = JSON.parse(chart.dataset.labels);

        expect(labels).toContain('Posts');
        expect(labels).toContain('Pages');
        expect(labels).toContain('Comments');
        expect(labels).toContain('Users');
    });
});

// ── WooCommerce products ──────────────────────────────────────────────────────
describe('StatsWidget — WooCommerce products', () => {
    it('shows Products row when stats.products is present', () => {
        mockStore({ stats: { posts: 1, products: 42 } });
        render(<StatsWidget />);

        expect(screen.getByText('Products')).toBeInTheDocument();
        expect(screen.getByText('42')).toBeInTheDocument();
    });

    it('does NOT show Products row when stats.products is absent', () => {
        mockStore({ stats: { posts: 1 } });
        render(<StatsWidget />);

        expect(screen.queryByText('Products')).not.toBeInTheDocument();
    });

    it('includes "Products" in chart labels when woo data present', () => {
        mockStore({ stats: { posts: 1, products: 99 }, isLoading: false });
        render(<StatsWidget />);

        const chart  = screen.getByTestId('doughnut-chart');
        const labels = JSON.parse(chart.dataset.labels);
        expect(labels).toContain('Products');
    });

    it('does NOT include "Products" in chart labels when absent', () => {
        mockStore({ stats: { posts: 1 }, isLoading: false });
        render(<StatsWidget />);

        const chart  = screen.getByTestId('doughnut-chart');
        const labels = JSON.parse(chart.dataset.labels);
        expect(labels).not.toContain('Products');
    });
});
