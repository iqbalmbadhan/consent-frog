export const CATEGORIES = {
    essential:    { label: 'Essential',    color: 'green',  model: 'exempt',  bg: '#d1fae5', text: '#065f46' },
    analytics:    { label: 'Analytics',    color: 'blue',   model: 'opt_out', bg: '#dbeafe', text: '#1d4ed8' },
    performance:  { label: 'Performance',  color: 'yellow', model: 'opt_out', bg: '#fef3c7', text: '#92400e' },
    marketing:    { label: 'Marketing',    color: 'red',    model: 'opt_in',  bg: '#fee2e2', text: '#991b1b' },
    unclassified: { label: 'Unclassified', color: 'gray',   model: 'opt_in',  bg: '#f3f4f6', text: '#6b7280' },
};

export const CONSENT_TYPES = {
    banner_accept:     'Accepted All',
    banner_reject:     'Rejected All',
    banner_customize:  'Customized',
    gpc_auto:          'GPC Signal',
    preference_update: 'Updated',
    withdrawal:        'Withdrawn',
};

export const STATUS_COLORS = {
    pending:    { bg: '#fef3c7', text: '#92400e' },
    verified:   { bg: '#dbeafe', text: '#1d4ed8' },
    processing: { bg: '#ede9fe', text: '#5b21b6' },
    completed:  { bg: '#d1fae5', text: '#065f46' },
    rejected:   { bg: '#fee2e2', text: '#991b1b' },
    granted:    { bg: '#d1fae5', text: '#065f46' },
    denied:     { bg: '#fee2e2', text: '#991b1b' },
    partial:    { bg: '#fef3c7', text: '#92400e' },
};
