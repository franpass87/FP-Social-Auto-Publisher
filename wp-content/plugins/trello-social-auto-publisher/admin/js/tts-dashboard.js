const { createElement, render, useState, useEffect } = wp.element;
const { SelectControl, Spinner } = wp.components;
const apiFetch = wp.apiFetch;

// Update the main notification manager reference
const NotificationManager = window.TTSNotifications;

// Loading Overlay Component
const LoadingOverlay = ({ show, message = 'Loading...' }) => {
    if (!show) return null;
    
    return createElement(
        'div',
        { className: 'tts-loading-overlay' },
        createElement(
            'div',
            { style: { textAlign: 'center' } },
            [
                createElement('div', { className: 'tts-loading', key: 'spinner' }),
                createElement('div', { key: 'message' }, message)
            ]
        )
    );
};

// Enhanced Status Badge Component
const StatusBadge = ({ status, showIndicator = true }) => {
    const getStatusConfig = (status) => {
        switch (status) {
            case 'published':
                return { class: 'online', text: 'Published', color: '#00a32a' };
            case 'scheduled':
                return { class: 'warning', text: 'Scheduled', color: '#f56e28' };
            case 'failed':
                return { class: 'offline', text: 'Failed', color: '#d63638' };
            default:
                return { class: 'warning', text: 'Unknown', color: '#666' };
        }
    };
    
    const config = getStatusConfig(status);
    
    return createElement(
        'span',
        { style: { display: 'flex', alignItems: 'center' } },
        [
            showIndicator && createElement('span', {
                className: `tts-status-indicator ${config.class}`,
                key: 'indicator'
            }),
            createElement('span', { key: 'text', style: { color: config.color } }, config.text)
        ]
    );
};

// Enhanced Post List with real-time updates
const PostList = ({ posts, onRefresh }) => {
    const [refreshing, setRefreshing] = useState(false);
    
    const handleRefresh = async () => {
        setRefreshing(true);
        try {
            await onRefresh();
            NotificationManager.show('Posts refreshed successfully', 'success');
        } catch (error) {
            NotificationManager.show('Failed to refresh posts', 'error');
        } finally {
            setRefreshing(false);
        }
    };
    
    return createElement(
        'div',
        { style: { position: 'relative' } },
        [
            createElement(LoadingOverlay, { show: refreshing, message: 'Refreshing posts...', key: 'overlay' }),
            createElement(
                'div',
                { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '15px' }, key: 'header' },
                [
                    createElement('h3', { key: 'title' }, 'Recent Posts'),
                    createElement(
                        'button',
                        {
                            className: 'tts-btn small',
                            onClick: handleRefresh,
                            disabled: refreshing,
                            key: 'refresh'
                        },
                        refreshing ? 'Refreshing...' : 'Refresh'
                    )
                ]
            ),
            createElement(
                'table',
                { className: 'widefat fixed', key: 'table' },
                [
                    createElement(
                        'thead',
                        { key: 'thead' },
                        createElement('tr', {}, [
                            createElement('th', { key: 'title' }, 'Title'),
                            createElement('th', { key: 'channels' }, 'Channels'),
                            createElement('th', { key: 'status' }, 'Status'),
                            createElement('th', { key: 'date' }, 'Publish Date'),
                            createElement('th', { key: 'actions' }, 'Actions'),
                        ])
                    ),
                    createElement(
                        'tbody',
                        { key: 'tbody' },
                        posts.length > 0 ? posts.map((post) => {
                            const channel = post.meta && post.meta._tts_social_channel ? post.meta._tts_social_channel : [];
                            const status = post.meta && post.meta._published_status ? post.meta._published_status : 'scheduled';
                            const publishDate = post.meta && post.meta._tts_publish_at ? post.meta._tts_publish_at : post.date;
                            const channels = Array.isArray(channel) ? channel.join(', ') : channel;
                            
                            return createElement('tr', { key: post.id }, [
                                createElement(
                                    'td',
                                    { key: 'title' },
                                    createElement(
                                        'a',
                                        {
                                            href: `post.php?post=${post.id}&action=edit`,
                                            className: 'tts-tooltip'
                                        },
                                        [
                                            post.title.rendered,
                                            createElement(
                                                'span',
                                                { className: 'tts-tooltiptext' },
                                                `Click to edit this post`
                                            )
                                        ]
                                    )
                                ),
                                createElement('td', { key: 'channels' }, channels || 'No channels'),
                                createElement(
                                    'td',
                                    { key: 'status' },
                                    createElement(StatusBadge, { status })
                                ),
                                createElement(
                                    'td',
                                    { key: 'date' },
                                    new Date(publishDate).toLocaleString()
                                ),
                                createElement(
                                    'td',
                                    { key: 'actions' },
                                    createElement(
                                        'a',
                                        {
                                            href: `admin.php?page=tts-social-posts&action=log&post=${post.id}`,
                                            className: 'tts-btn small secondary'
                                        },
                                        'View Log'
                                    )
                                )
                            ]);
                        }) : [
                            createElement(
                                'tr',
                                { key: 'empty' },
                                createElement(
                                    'td',
                                    { colSpan: 5, style: { textAlign: 'center', padding: '20px' } },
                                    'No posts found.'
                                )
                            )
                        ]
                    )
                ]
            )
        ]
    );
};

// Enhanced Dashboard Component with real-time updates
const Dashboard = () => {
    const [posts, setPosts] = useState([]);
    const [channel, setChannel] = useState('');
    const [status, setStatus] = useState('');
    const [loading, setLoading] = useState(true);
    const [autoRefresh, setAutoRefresh] = useState(false);
    const [lastUpdate, setLastUpdate] = useState(new Date());

    const fetchPosts = async () => {
        try {
            const data = await apiFetch({ 
                path: '/wp/v2/tts_social_post?per_page=100&status=any&_fields=id,title,meta,date' 
            });
            setPosts(data);
            setLastUpdate(new Date());
            return data;
        } catch (error) {
            NotificationManager.show('Failed to fetch posts', 'error');
            throw error;
        }
    };

    useEffect(() => {
        fetchPosts().finally(() => setLoading(false));
    }, []);

    // Auto-refresh every 30 seconds when enabled
    useEffect(() => {
        let interval;
        if (autoRefresh) {
            interval = setInterval(() => {
                fetchPosts();
            }, 30000);
        }
        return () => {
            if (interval) clearInterval(interval);
        };
    }, [autoRefresh]);

    const filtered = posts.filter((post) => {
        const postChannel = post.meta && post.meta._tts_social_channel ? post.meta._tts_social_channel : [];
        const postStatus = post.meta && post.meta._published_status ? post.meta._published_status : 'scheduled';
        const channels = Array.isArray(postChannel) ? postChannel : [postChannel];
        return (!channel || channels.includes(channel)) && (!status || status === postStatus);
    });

    const toggleAutoRefresh = () => {
        setAutoRefresh(!autoRefresh);
        NotificationManager.show(
            `Auto-refresh ${!autoRefresh ? 'enabled' : 'disabled'}`,
            'info'
        );
    };

    if (loading) {
        return createElement(
            'div',
            { style: { textAlign: 'center', padding: '40px' } },
            [
                createElement(Spinner, { key: 'spinner' }),
                createElement('p', { key: 'text' }, 'Loading dashboard...')
            ]
        );
    }

    return createElement('div', { className: 'tts-enhanced-dashboard' }, [
        // Dashboard Header
        createElement(
            'div',
            {
                className: 'tts-dashboard-header',
                style: {
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    marginBottom: '20px',
                    padding: '20px',
                    background: '#fff',
                    borderRadius: '8px',
                    boxShadow: '0 2px 8px rgba(0,0,0,0.08)'
                },
                key: 'header'
            },
            [
                createElement('div', { key: 'info' }, [
                    createElement('h2', { style: { margin: '0 0 5px 0' } }, 'Advanced Dashboard'),
                    createElement(
                        'p',
                        { style: { margin: 0, color: '#666', fontSize: '14px' } },
                        `Last updated: ${lastUpdate.toLocaleTimeString()}`
                    )
                ]),
                createElement(
                    'div',
                    { style: { display: 'flex', gap: '10px' }, key: 'controls' },
                    [
                        createElement(
                            'button',
                            {
                                className: `tts-btn ${autoRefresh ? 'danger' : 'secondary'}`,
                                onClick: toggleAutoRefresh,
                                key: 'refresh-toggle'
                            },
                            autoRefresh ? 'Stop Auto-refresh' : 'Start Auto-refresh'
                        ),
                        createElement(
                            'button',
                            {
                                className: 'tts-btn',
                                onClick: () => fetchPosts(),
                                key: 'manual-refresh'
                            },
                            'Manual Refresh'
                        )
                    ]
                )
            ]
        ),

        // Filters Section
        createElement(
            'div',
            {
                className: 'tts-filters',
                style: {
                    display: 'flex',
                    gap: '20px',
                    marginBottom: '20px',
                    padding: '15px',
                    background: '#fff',
                    borderRadius: '8px',
                    boxShadow: '0 2px 8px rgba(0,0,0,0.08)'
                },
                key: 'filters'
            },
            [
                createElement('div', { style: { flex: 1 }, key: 'channel-filter' }, [
                    createElement(SelectControl, {
                        label: 'Filter by Channel',
                        value: channel,
                        options: [
                            { label: 'All Channels', value: '' },
                            { label: 'Facebook', value: 'facebook' },
                            { label: 'Instagram', value: 'instagram' },
                            { label: 'YouTube', value: 'youtube' },
                            { label: 'TikTok', value: 'tiktok' }
                        ],
                        onChange: (value) => setChannel(value)
                    })
                ]),
                createElement('div', { style: { flex: 1 }, key: 'status-filter' }, [
                    createElement(SelectControl, {
                        label: 'Filter by Status',
                        value: status,
                        options: [
                            { label: 'All Statuses', value: '' },
                            { label: 'Scheduled', value: 'scheduled' },
                            { label: 'Published', value: 'published' },
                            { label: 'Failed', value: 'failed' }
                        ],
                        onChange: (value) => setStatus(value)
                    })
                ]),
                createElement(
                    'div',
                    { style: { display: 'flex', alignItems: 'end' }, key: 'stats' },
                    createElement(
                        'div',
                        { style: { padding: '5px 10px', background: '#f0f0f1', borderRadius: '4px' } },
                        `${filtered.length} of ${posts.length} posts`
                    )
                )
            ]
        ),

        // Enhanced Post List
        createElement(
            'div',
            {
                style: {
                    background: '#fff',
                    borderRadius: '8px',
                    boxShadow: '0 2px 8px rgba(0,0,0,0.08)',
                    overflow: 'hidden'
                },
                key: 'posts'
            },
            createElement(PostList, { posts: filtered, onRefresh: fetchPosts })
        )
    ]);
};

// Initialize the enhanced dashboard
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('tts-dashboard-root');
    if (root) {
        // Show welcome notification
        setTimeout(() => {
            NotificationManager.success('Enhanced dashboard loaded successfully!', { duration: 3000 });
        }, 1000);
        
        // Render the enhanced dashboard
        render(createElement(Dashboard), root);
    }
});
