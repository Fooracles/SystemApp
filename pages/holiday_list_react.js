// Complete React Application for Holiday List Page
// This file contains all React components for the holiday management page

const { useState, useEffect, useRef, useCallback } = React;

// Helper function
function getHolidayStatus(holidayDate) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const holiday = new Date(holidayDate);
    holiday.setHours(0, 0, 0, 0);
    const diffTime = holiday - today;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays < 0) return 'past';
    if (diffDays === 0) return 'current';
    return 'upcoming';
}

// Statistics Component
function Statistics({ holidays }) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    const total = holidays.length;
    const upcoming = holidays.filter(holiday => {
        const holidayDate = new Date(holiday.holiday_date);
        holidayDate.setHours(0, 0, 0, 0);
        return holidayDate > today;
    }).length;
    const past = holidays.filter(holiday => {
        const holidayDate = new Date(holiday.holiday_date);
        holidayDate.setHours(0, 0, 0, 0);
        return holidayDate < today;
    }).length;

    return React.createElement('div', { className: 'holiday-stats' },
        React.createElement('div', { className: 'stat-item' },
            React.createElement('span', { className: 'stat-number' }, total),
            React.createElement('span', { className: 'stat-label' }, 'Total Holidays')
        ),
        React.createElement('div', { className: 'stat-item' },
            React.createElement('span', { className: 'stat-number' }, upcoming),
            React.createElement('span', { className: 'stat-label' }, 'Upcoming')
        ),
        React.createElement('div', { className: 'stat-item' },
            React.createElement('span', { className: 'stat-number' }, past),
            React.createElement('span', { className: 'stat-label' }, 'Past')
        )
    );
}

// Filter Buttons Component
function FilterButtons({ currentFilter, onFilterChange }) {
    const filters = [
        { key: 'all', label: 'All Holidays' },
        { key: 'upcoming', label: 'Upcoming' },
        { key: 'past', label: 'Past' }
    ];

    return React.createElement('div', { className: 'filter-buttons' },
        ...filters.map(filter =>
            React.createElement('button', {
                key: filter.key,
                className: `filter-btn ${currentFilter === filter.key ? 'active' : ''}`,
                onClick: () => onFilterChange(filter.key)
            }, filter.label)
        )
    );
}

// Holiday Table Component
function HolidayTable({ holidays, canDelete, onDelete }) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const sortedHolidays = [...holidays].sort((a, b) => {
        const dateA = new Date(a.holiday_date);
        dateA.setHours(0, 0, 0, 0);
        const dateB = new Date(b.holiday_date);
        dateB.setHours(0, 0, 0, 0);
        
        const isUpcomingA = dateA >= today;
        const isUpcomingB = dateB >= today;
        
        if (isUpcomingA && !isUpcomingB) return -1;
        if (!isUpcomingA && isUpcomingB) return 1;
        
        return dateA - dateB;
    });

    if (sortedHolidays.length === 0) {
        return React.createElement('div', { className: 'no-holidays' },
            React.createElement('i', { className: 'fas fa-calendar-times' }),
            React.createElement('h5', null, 'No holidays found'),
            React.createElement('p', null, 'Try adjusting your filter criteria')
        );
    }

    return React.createElement('table', { className: 'holiday-table' },
        React.createElement('thead', null,
            React.createElement('tr', null,
                React.createElement('th', null, 'Date'),
                React.createElement('th', null, 'Holiday Name'),
                React.createElement('th', null, 'Status'),
                canDelete && React.createElement('th', null,
                    React.createElement('i', { className: 'fas fa-cog me-1' }),
                    'Actions'
                )
            )
        ),
        React.createElement('tbody', null,
            ...sortedHolidays.map(holiday => {
                const date = new Date(holiday.holiday_date + 'T00:00:00');
                const formattedDate = date.toLocaleDateString('en-GB', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric'
                });
                const status = getHolidayStatus(holiday.holiday_date);
                const statusClass = `status-${status}`;
                const statusText = status === 'upcoming' ? 'Upcoming' : status === 'current' ? 'Today' : 'Past';
                const statusIcon = status === 'upcoming' ? 'fa-clock' : status === 'current' ? 'fa-star' : 'fa-history';
                const pastClass = status === 'past' ? 'past-holiday' : '';

                return React.createElement('tr', { key: holiday.id, className: pastClass },
                    React.createElement('td', null,
                        React.createElement('div', { className: 'holiday-date' },
                            React.createElement('i', { className: 'fas fa-calendar-alt' }),
                            formattedDate
                        )
                    ),
                    React.createElement('td', null,
                        React.createElement('div', { className: 'holiday-name' }, holiday.holiday_name)
                    ),
                    React.createElement('td', null,
                        React.createElement('span', { className: `holiday-status ${statusClass}` },
                            React.createElement('i', { className: `fas ${statusIcon}` }),
                            statusText
                        )
                    ),
                    canDelete && React.createElement('td', null,
                        React.createElement('div', { className: 'action-buttons' },
                            React.createElement('button', {
                                className: 'btn-delete delete-holiday',
                                onClick: () => onDelete(holiday.id, holiday.holiday_name)
                            },
                                React.createElement('i', { className: 'fas fa-trash' }),
                                ' Delete'
                            )
                        )
                    )
                );
            })
        )
    );
}

// Add Holiday Modal Component
function AddHolidayModal({ isOpen, onClose, onSuccess }) {
    const [activeTab, setActiveTab] = useState('single');
    const [holidayDate, setHolidayDate] = useState('');
    const [holidayName, setHolidayName] = useState('');
    const [file, setFile] = useState(null);
    const [loading, setLoading] = useState(false);
    const dateInputRef = useRef(null);

    useEffect(() => {
        if (isOpen && activeTab === 'single' && dateInputRef.current) {
            $(dateInputRef.current).datepicker({
                format: 'yyyy-mm-dd',
                autoclose: true,
                todayHighlight: true,
                todayBtn: "linked"
            });
        }
    }, [isOpen, activeTab]);

    const handleSingleSubmit = async (e) => {
        e.preventDefault();
        if (!holidayDate || !holidayName) {
            window.showToast('Please fill in both date and name.', 'danger');
            return;
        }

        setLoading(true);
        try {
            const formData = new FormData();
            formData.append('action', 'add_holiday');
            formData.append('holiday_date', holidayDate);
            formData.append('holiday_name', holidayName);

            const response = await fetch('../ajax/holiday_handler.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            window.showToast(result.message, result.status);
            
            if (result.status === 'success') {
                setHolidayDate('');
                setHolidayName('');
                if (dateInputRef.current) {
                    $(dateInputRef.current).datepicker('update', '');
                }
                onSuccess();
                onClose();
            }
        } catch (error) {
            window.showToast('Error communicating with server.', 'danger');
        } finally {
            setLoading(false);
        }
    };

    const handleBulkSubmit = async (e) => {
        e.preventDefault();
        if (!file) {
            window.showToast('Please select a file to upload.', 'danger');
            return;
        }

        setLoading(true);
        try {
            const formData = new FormData();
            formData.append('action', 'bulk_upload_holidays');
            formData.append('file', file);

            const response = await fetch('../ajax/holiday_handler.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            window.showToast(result.message, result.status);
            
            if (result.status === 'success') {
                setFile(null);
                onSuccess();
                onClose();
            }
        } catch (error) {
            window.showToast('Error communicating with server.', 'danger');
        } finally {
            setLoading(false);
        }
    };

    const handleFileChange = (e) => {
        const selectedFile = e.target.files[0];
        if (selectedFile) {
            const fileExtension = selectedFile.name.split('.').pop().toLowerCase();
            if (fileExtension === 'csv' || fileExtension === 'xls' || fileExtension === 'xlsx') {
                setFile(selectedFile);
            } else {
                window.showToast('Please select a CSV or Excel file.', 'danger');
                e.target.value = '';
            }
        }
    };

    const downloadTemplate = () => {
        const csvContent = 'holiday_date,holiday_name\n2024-01-01,New Year\'s Day\n2024-12-25,Christmas';
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', 'holiday_template.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    if (!isOpen) return null;

    return ReactDOM.createPortal(
        React.createElement('div', { className: 'holiday-modal-overlay', onClick: onClose },
            React.createElement('div', { className: 'holiday-modal-content', onClick: (e) => e.stopPropagation() },
                React.createElement('div', { className: 'holiday-modal-header' },
                    React.createElement('h3', null,
                        React.createElement('i', { className: 'fas fa-calendar-plus me-2' }),
                        'Add Holiday'
                    ),
                    React.createElement('button', { className: 'holiday-modal-close', onClick: onClose },
                        React.createElement('i', { className: 'fas fa-times' })
                    )
                ),
                React.createElement('div', { className: 'holiday-modal-body' },
                    React.createElement('div', { className: 'holiday-modal-tabs' },
                        React.createElement('button', {
                            className: `holiday-modal-tab ${activeTab === 'single' ? 'active' : ''}`,
                            onClick: () => setActiveTab('single')
                        },
                            React.createElement('i', { className: 'fas fa-plus-circle me-2' }),
                            'Single Holiday'
                        ),
                        React.createElement('button', {
                            className: `holiday-modal-tab ${activeTab === 'bulk' ? 'active' : ''}`,
                            onClick: () => setActiveTab('bulk')
                        },
                            React.createElement('i', { className: 'fas fa-upload me-2' }),
                            'Bulk Upload'
                        )
                    ),
                    activeTab === 'single' ? (
                        React.createElement('form', { onSubmit: handleSingleSubmit },
                            React.createElement('div', { className: 'holiday-modal-form-group' },
                                React.createElement('label', { htmlFor: 'modal-holiday-date' },
                                    React.createElement('i', { className: 'fas fa-calendar me-2' }),
                                    'Holiday Date'
                                ),
                                React.createElement('div', { className: 'datepicker-input-wrapper' },
                                    React.createElement('i', { className: 'fas fa-calendar' }),
                                    React.createElement('input', {
                                        ref: dateInputRef,
                                        type: 'text',
                                        id: 'modal-holiday-date',
                                        className: 'form-control datepicker',
                                        value: holidayDate,
                                        onChange: (e) => setHolidayDate(e.target.value),
                                        placeholder: 'Select date',
                                        required: true,
                                        autoComplete: 'off'
                                    })
                                )
                            ),
                            React.createElement('div', { className: 'holiday-modal-form-group' },
                                React.createElement('label', { htmlFor: 'modal-holiday-name' },
                                    React.createElement('i', { className: 'fas fa-tag me-2' }),
                                    'Holiday Name'
                                ),
                                React.createElement('input', {
                                    type: 'text',
                                    id: 'modal-holiday-name',
                                    className: 'form-control',
                                    value: holidayName,
                                    onChange: (e) => setHolidayName(e.target.value),
                                    placeholder: 'Enter holiday name',
                                    required: true
                                })
                            ),
                            React.createElement('div', { className: 'holiday-modal-footer' },
                                React.createElement('button', {
                                    type: 'button',
                                    className: 'holiday-modal-btn holiday-modal-btn-secondary',
                                    onClick: onClose
                                }, 'Cancel'),
                                React.createElement('button', {
                                    type: 'submit',
                                    className: 'holiday-modal-btn holiday-modal-btn-primary',
                                    disabled: loading
                                },
                                    loading ? (
                                        React.createElement(React.Fragment, null,
                                            React.createElement('div', { className: 'loading-spinner me-2' }),
                                            'Adding...'
                                        )
                                    ) : (
                                        React.createElement(React.Fragment, null,
                                            React.createElement('i', { className: 'fas fa-plus me-2' }),
                                            'Add Holiday'
                                        )
                                    )
                                )
                            )
                        )
                    ) : (
                        React.createElement('form', { onSubmit: handleBulkSubmit },
                            React.createElement('div', { className: 'holiday-modal-info' },
                                React.createElement('i', { className: 'fas fa-info-circle' }),
                                ' Upload a CSV or Excel file with columns: ',
                                React.createElement('strong', null, 'holiday_date'),
                                ' and ',
                                React.createElement('strong', null, 'holiday_name')
                            ),
                            React.createElement('div', { className: 'holiday-modal-form-group' },
                                React.createElement('label', { htmlFor: 'bulk-file-upload' },
                                    React.createElement('i', { className: 'fas fa-file-upload me-2' }),
                                    'Select File (CSV or Excel)'
                                ),
                                React.createElement('input', {
                                    type: 'file',
                                    id: 'bulk-file-upload',
                                    accept: '.csv,.xls,.xlsx',
                                    onChange: handleFileChange,
                                    required: true
                                }),
                                file && React.createElement('p', {
                                    style: { marginTop: '0.5rem', color: 'var(--dark-text-secondary)', fontSize: '0.85rem' }
                                },
                                    React.createElement('i', { className: 'fas fa-check-circle me-2', style: { color: '#10b981' } }),
                                    'Selected: ',
                                    file.name
                                )
                            ),
                            React.createElement('div', { className: 'holiday-modal-form-group' },
                                React.createElement('button', {
                                    type: 'button',
                                    className: 'holiday-modal-btn holiday-modal-btn-secondary',
                                    onClick: downloadTemplate
                                },
                                    React.createElement('i', { className: 'fas fa-download me-2' }),
                                    'Download Template'
                                )
                            ),
                            React.createElement('div', { className: 'holiday-modal-footer' },
                                React.createElement('button', {
                                    type: 'button',
                                    className: 'holiday-modal-btn holiday-modal-btn-secondary',
                                    onClick: onClose
                                }, 'Cancel'),
                                React.createElement('button', {
                                    type: 'submit',
                                    className: 'holiday-modal-btn holiday-modal-btn-primary',
                                    disabled: loading || !file
                                },
                                    loading ? (
                                        React.createElement(React.Fragment, null,
                                            React.createElement('div', { className: 'loading-spinner me-2' }),
                                            'Uploading...'
                                        )
                                    ) : (
                                        React.createElement(React.Fragment, null,
                                            React.createElement('i', { className: 'fas fa-upload me-2' }),
                                            'Upload Holidays'
                                        )
                                    )
                                )
                            )
                        )
                    )
                )
            )
        ),
        document.body
    );
}

// Main Holiday List App Component
function HolidayListApp() {
    const [holidays, setHolidays] = useState(initialHolidays);
    const [filteredHolidays, setFilteredHolidays] = useState(initialHolidays);
    const [currentFilter, setCurrentFilter] = useState('all');
    const [loading, setLoading] = useState(false);
    const [isModalOpen, setIsModalOpen] = useState(false);

    useEffect(() => {
        filterHolidays();
    }, [holidays, currentFilter]);

    const filterHolidays = useCallback(() => {
        let filtered = [...holidays];
        
        if (currentFilter !== 'all') {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            filtered = filtered.filter(holiday => {
                const holidayDate = new Date(holiday.holiday_date);
                holidayDate.setHours(0, 0, 0, 0);
                const status = getHolidayStatus(holiday.holiday_date);
                
                switch (currentFilter) {
                    case 'upcoming':
                        return status === 'upcoming';
                    case 'past':
                        return status === 'past';
                    default:
                        return true;
                }
            });
        }
        
        setFilteredHolidays(filtered);
    }, [holidays, currentFilter]);

    const fetchHolidays = useCallback(async () => {
        setLoading(true);
        try {
            const response = await fetch('../ajax/holiday_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_holidays'
            });
            const result = await response.json();
            if (result.status === 'success') {
                setHolidays(result.holidays);
            } else {
                window.showToast(result.message || 'Error fetching holidays.', 'danger');
            }
        } catch (error) {
            window.showToast('Failed to communicate with server.', 'danger');
        } finally {
            setLoading(false);
        }
    }, []);

    const handleDelete = useCallback(async (holidayId, holidayName) => {
        if (!confirm(`Are you sure you want to delete "${holidayName}"?\n\nThis action cannot be undone.`)) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'delete_holiday');
            formData.append('holiday_id', holidayId);

            const response = await fetch('../ajax/holiday_handler.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            window.showToast(result.message, result.status);
            
            if (result.status === 'success') {
                fetchHolidays();
            }
        } catch (error) {
            window.showToast('Error communicating with server.', 'danger');
        }
    }, [fetchHolidays]);

    const handleModalSuccess = useCallback(() => {
        fetchHolidays();
    }, [fetchHolidays]);

    return React.createElement('div', { className: 'holiday-page-container' },
        React.createElement('div', { className: 'container' },
            // Header Section
            React.createElement('div', { className: 'holiday-header' },
                React.createElement('div', { className: 'd-flex justify-content-between align-items-center' },
                    React.createElement('div', null,
                        React.createElement('h1', { className: 'mb-2' },
                            React.createElement('i', { className: 'fas fa-calendar-alt me-2' }),
                            pageTitle
                        ),
                        React.createElement('p', { className: 'mb-0 opacity-75' }, 'Manage and view all company holidays')
                    ),
                    username && React.createElement('div', { className: 'text-end' },
                        React.createElement('p', { className: 'mb-0' }, 'Welcome back,'),
                        React.createElement('strong', null, username)
                    )
                ),
                React.createElement(Statistics, { holidays: filteredHolidays })
            ),

            // Table Section
            React.createElement('div', { className: 'holiday-table-container' },
                React.createElement('div', { className: 'holiday-table-header' },
                    React.createElement('div', { className: 'd-flex justify-content-between align-items-center' },
                        React.createElement('span', null, 'Holiday Calendar'),
                        React.createElement('div', { className: 'd-flex align-items-center', style: { gap: '1rem' } },
                            React.createElement(FilterButtons, {
                                currentFilter: currentFilter,
                                onFilterChange: setCurrentFilter
                            }),
                            isAdminUser && React.createElement('button', {
                                className: 'btn-add-holiday',
                                onClick: () => setIsModalOpen(true)
                            },
                                React.createElement('i', { className: 'fas fa-plus me-2' }),
                                'Add Holiday'
                            )
                        )
                    )
                ),
                React.createElement('div', { className: 'table-responsive' },
                    loading ? (
                        React.createElement('div', { className: 'text-center p-4' },
                            React.createElement('div', { className: 'loading-spinner' }),
                            React.createElement('p', { className: 'mt-2' }, 'Loading holidays...')
                        )
                    ) : (
                        React.createElement(HolidayTable, {
                            holidays: filteredHolidays,
                            canDelete: isAdminUser,
                            onDelete: handleDelete
                        })
                    )
                )
            )
        ),
        React.createElement(AddHolidayModal, {
            isOpen: isModalOpen,
            onClose: () => setIsModalOpen(false),
            onSuccess: handleModalSuccess
        })
    );
}

// Initialize React App
const root = ReactDOM.createRoot(document.getElementById('holiday-react-root'));
root.render(React.createElement(HolidayListApp));

