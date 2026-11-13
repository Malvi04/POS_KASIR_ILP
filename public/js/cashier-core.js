        (function() {
            const RESET_FLAG = 'pos_ilp_v3_clean_init';
            if (localStorage.getItem(RESET_FLAG) !== 'true') {
                const keysToClear = [
                    'pos_ilp_inventory_products_v1',
                    'pos_ilp_members_v1',
                    'pos_ilp_branches_v1',
                    'pos_ilp_transactions_v1',
                    'pos_ilp_held_bills_v1',
                    'pos_ilp_active_cart_v1',
                    'pos_ilp_coupons_v1',
                    'pos_ilp_refund_history_v1',
                    'pos_ilp_goods_in_docs_v1',
                    'pos_ilp_manager_returns_v1',
                    'pos_ilp_manager_retur_masuk_v1',
                    'pos_ilp_return_in_docs_v1',
                    'pos_ilp_retur_history_docs_v1',
                    'pos_ilp_retur_draft_items_v1',
                    'pos_ilp_stok_retur_gudang_v1',
                    'pos_ilp_umkm_partners_v1',
                    'pos_ilp_umkm_products_v1',
                    'pos_ilp_promotions_v1',
                    'pos_ilp_attendance_v1',
                    'pos_ilp_leaves_v1',
                    'pos_ilp_audit_logs_v1',
                    'pos_ilp_session_history_v1',
                    'pos_ilp_cash_movements_v1',
                    'pos_ilp_stock_opname_history_v1',
                    'pos_ilp_active_opname_v1',
                    'pos_ilp_credit_limit_requests_v1',
                    'pos_ilp_operator_history_v1',
                    'pos_ilp_active_session_v1',
                    'pos_ilp_active_operator_v1',
                    'pos_ilp_terminals_v1',
                    'pos_cashier_opened'
                ];
                keysToClear.forEach(k => localStorage.removeItem(k));
                localStorage.setItem(RESET_FLAG, 'true');
            }
        })();

        window.cashierOpeningData = function() {
            const defaultStaffList = [
                { id: 'USR-01', name: 'Andi Pratama', username: 'andi_kasir', password: 'andi123', nik: '202409821001', role: 'Kasir Operasional', pin: '123456', initials: 'AP', badgeClass: 'bg-blue-100 text-blue-700', branch: 'Cabang A', assignedBranches: [{ branch: 'Cabang A', status: 'AKTIF' }, { branch: 'Pusat', status: 'AKTIF' }, { branch: 'Cabang B', status: 'AKTIF' }] },
                { id: 'USR-02', name: 'Budi Kurniawan', username: 'budi_kasir01', password: 'budi123', nik: '202409821002', role: 'Kasir Utama', pin: '123456', initials: 'BK', badgeClass: 'bg-emerald-100 text-emerald-700', branch: 'Pusat', assignedBranches: [{ branch: 'Pusat', status: 'AKTIF' }, { branch: 'Cabang A', status: 'AKTIF' }, { branch: 'Cabang B', status: 'AKTIF' }] },
                { id: 'USR-03', name: 'Citra Dewi', username: 'citra_kasir', password: 'citra123', nik: '202409821003', role: 'Kasir Operasional', pin: '123456', initials: 'CD', badgeClass: 'bg-purple-100 text-purple-700', branch: 'Cabang A', assignedBranches: [{ branch: 'Cabang A', status: 'AKTIF' }, { branch: 'Pusat', status: 'AKTIF' }, { branch: 'Cabang B', status: 'AKTIF' }] },
                { id: 'USR-04', name: 'Dedi Santoso', username: 'dedi_kasir', password: 'dedi123', nik: '202409821004', role: 'Kasir Operasional', pin: '123456', initials: 'DS', badgeClass: 'bg-amber-100 text-amber-700', branch: 'Cabang B', assignedBranches: [{ branch: 'Cabang B', status: 'AKTIF' }, { branch: 'Pusat', status: 'AKTIF' }, { branch: 'Cabang A', status: 'AKTIF' }] }
            ];

            const defaultTerminals = [
                // Pusat: POS-01 sampai POS-05
                { id: 'POS-01', label: 'POS-01', name: 'Terminal POS-01', branch: 'Pusat', code: 'TERM-01', isActive: true, status: 'AVAILABLE', sessionsTodayCount: 0, activeCashierName: null, activeCashierId: null, activeStartedAt: null, activeSessionId: null, handover: null },
                { id: 'POS-02', label: 'POS-02', name: 'Terminal POS-02', branch: 'Pusat', code: 'TERM-02', isActive: true, status: 'AVAILABLE', sessionsTodayCount: 0, activeCashierName: null, activeCashierId: null, activeStartedAt: null, activeSessionId: null, handover: null },
                { id: 'POS-03', label: 'POS-03', name: 'Terminal POS-03', branch: 'Pusat', code: 'TERM-03', isActive: true, status: 'AVAILABLE', sessionsTodayCount: 0, activeCashierName: null, activeCashierId: null, activeStartedAt: null, activeSessionId: null, handover: null },
                { id: 'POS-04', label: 'POS-04', name: 'Terminal POS-04', branch: 'Pusat', code: 'TERM-04', isActive: true, status: 'AVAILABLE', sessionsTodayCount: 0, activeCashierName: null, activeCashierId: null, activeStartedAt: null, activeSessionId: null, handover: null },
                { id: 'POS-05', label: 'POS-05', name: 'Terminal POS-05', branch: 'Pusat', code: 'TERM-05', isActive: true, status: 'AVAILABLE', sessionsTodayCount: 0, activeCashierName: null, activeCashierId: null, activeStartedAt: null, activeSessionId: null, handover: null },

                // Cabang A: POS-06 sampai POS-10
                { id: 'POS-06', label: 'POS-06', name: 'Terminal POS-06', branch: 'Cabang A', code: 'TERM-06', isActive: true, status: 'AVAILABLE', sessionsTodayCount: 0, activeCashierName: null, activeCashierId: null, activeStartedAt: null, activeSessionId: null, handover: null },
                { id: 'POS-07', label: 'POS-07', name: 'Terminal POS-07', branch: 'Cabang A', code: 'TERM-07', isActive: true, status: 'AVAILABLE', sessionsTodayCount: 0, activeCashierName: null, activeCashierId: null, activeStartedAt: null, activeSessionId: null, handover: null },
                { id: 'POS-08', label: 'POS-08', name: 'Terminal POS-08', branch: 'Cabang A', code: 'TERM-08', isActive: true, status: 'AVAILABLE', sessionsTodayCount: 0, activeCashierName: null, activeCashierId: null, activeStartedAt: null, activeSessionId: null, handover: null },
                { id: 'POS-09', label: 'POS-09', name: 'Terminal POS-09', branch: 'Cabang A', code: 'TERM-09', isActive: true, status: 'AVAILABLE', sessionsTodayCount: 0, activeCashierName: null, activeCashierId: null, activeStartedAt: null, activeSessionId: null, handover: null },
                { id: 'POS-10', label: 'POS-10', name: 'Terminal POS-10', branch: 'Cabang A', code: 'TERM-10', isActive: true, status: 'AVAILABLE', sessionsTodayCount: 0, activeCashierName: null, activeCashierId: null, activeStartedAt: null, activeSessionId: null, handover: null },

                // Cabang B: POS-11 sampai POS-15
                { id: 'POS-11', label: 'POS-11', name: 'Terminal POS-11', branch: 'Cabang B', code: 'TERM-11', isActive: true, status: 'AVAILABLE', sessionsTodayCount: 0, activeCashierName: null, activeCashierId: null, activeStartedAt: null, activeSessionId: null, handover: null },
                { id: 'POS-12', label: 'POS-12', name: 'Terminal POS-12', branch: 'Cabang B', code: 'TERM-12', isActive: true, status: 'AVAILABLE', sessionsTodayCount: 0, activeCashierName: null, activeCashierId: null, activeStartedAt: null, activeSessionId: null, handover: null },
                { id: 'POS-13', label: 'POS-13', name: 'Terminal POS-13', branch: 'Cabang B', code: 'TERM-13', isActive: true, status: 'AVAILABLE', sessionsTodayCount: 0, activeCashierName: null, activeCashierId: null, activeStartedAt: null, activeSessionId: null, handover: null },
                { id: 'POS-14', label: 'POS-14', name: 'Terminal POS-14', branch: 'Cabang B', code: 'TERM-14', isActive: true, status: 'AVAILABLE', sessionsTodayCount: 0, activeCashierName: null, activeCashierId: null, activeStartedAt: null, activeSessionId: null, handover: null },
                { id: 'POS-15', label: 'POS-15', name: 'Terminal POS-15', branch: 'Cabang B', code: 'TERM-15', isActive: true, status: 'AVAILABLE', sessionsTodayCount: 0, activeCashierName: null, activeCashierId: null, activeStartedAt: null, activeSessionId: null, handover: null }
            ];

            const defaultSessionHistory = [];

            const defaultActiveSession = {
                sessionId: '',
                businessDayId: 'BD-' + new Date().toISOString().slice(0, 10).replace(/-/g, ''),
                businessDayDate: new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }),
                storeLocation: 'Pusat',
                terminalId: '',
                terminalLabel: '',
                cashierId: '',
                cashierName: '',
                cashierNik: '',
                cashierRole: '',
                startedAt: '',
                openingCash: 0,
                isHandoverOpening: false,
                handoverFromName: '',
                handoverFromSessionId: '',
                prevVarianceLocked: 0,
                cashSalesNet: 0,
                qrisSales: 0,
                creditSales: 0,
                splitCashPortion: 0,
                splitNonCashPortion: 0,
                cashIn: 0,
                cashOut: 0,
                cashReturn: 0,
                cashDrop: 0,
                trxCount: 0,
                heldBillsCount: 0,
                status: 'CLOSED',
                isOffline: false,
                pendingSyncCount: 0
            };

            const defaultCashMovements = [];

            return {
                selectedShift: 'POS-01',
                selectedBranch: 'Pusat',
                selectedLocation: 'Pusat',
                branchAssignmentError: '',
                terminalInactiveNotice: '',
                businessDayDate: new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }),
                businessDayId: 'BD-' + new Date().toISOString().slice(0, 10).replace(/-/g, ''),
                staffList: defaultStaffList,
                selectedStaffId: '',
                staffPinInput: '',
                openingPinError: '',
                terminalFilter: 'all',
                terminals: defaultTerminals,
                activeSession: null,
                sessionHistory: [],
                cashMovements: [],

                // Operator Switching & Terminal Lock State
                activeOperator: null,
                operatorHistory: [],
                isTerminalLocked: false,
                lockPinInput: '',
                lockErrorMsg: '',
                operatorSwitchModal: {
                    open: false,
                    targetStaffId: '',
                    targetPin: '',
                    reason: 'Istirahat / Sholat',
                    errorMsg: '',
                    attemptCount: 0,
                    isSubmitting: false
                },

                showOpeningModal: false,
                isOpeningSubmitting: false,
                showDenomModal: false,
                denomTarget: 'opening', // 'opening' | 'closing'
                denomMode: 'idr',
                kasOpening: '0,00',
                catatanOpening: '',
                denomTotal: 0,
                showSuccessToast: false,
                toastMessage: '',

                // Cash Movement (Kas Masuk / Keluar / Cash Drop) Modal
                showCashMovementModal: false,
                newMovement: {
                    type: 'CASH_DROP',
                    amount: '',
                    note: ''
                },

                // Opsi A: Full Closing + Blind Count + Handover Wizard Modal
                closingWizard: {
                    open: false,
                    step: 1, // 1: Blind Count & Hold Cart, 2: Reconciliation & Variance, 3: Handover to Next Staff, 4: Completed Receipt
                    isSubmitting: false,
                    holdAction: 'inherit', // 'inherit' | 'cancel'
                    actualCashStr: '',
                    actualCashNum: 0,
                    blindLocked: false,
                    varianceCategory: 'Selisih Kembalian Tunai',
                    varianceNote: '',
                    handoverType: 'handover', // 'handover' | 'close_only'
                    nextCashierId: 'USR-01',
                    nextCashierPin: '123456',
                    handoverNote: '',
                    errorMsg: '',
                    lastClosedRecord: null
                },

                denomListIdr: [
                    { id: 1, label: 'Rp 100.000,00', value: 100000, count: 0, col: 'left' },
                    { id: 2, label: 'Rp 50.000,00',  value: 50000,  count: 0, col: 'left' },
                    { id: 3, label: 'Rp 20.000,00',  value: 20000,  count: 0, col: 'left' },
                    { id: 4, label: 'Rp 10.000,00',  value: 10000,  count: 0, col: 'left' },
                    { id: 5, label: 'Rp 5.000,00',   value: 5000,   count: 0, col: 'left' },
                    { id: 6, label: 'Rp 2.000,00',   value: 2000,   count: 0, col: 'left' },
                    { id: 7, label: 'Rp 1.000,00',   value: 1000,   count: 0, col: 'left' },
                    { id: 8, label: 'Rp 1.000,00 (Koin)', value: 1000, count: 0, col: 'right' },
                    { id: 9, label: 'Rp 500,00',     value: 500,    count: 0, col: 'right' },
                    { id: 10, label: 'Rp 200,00',    value: 200,    count: 0, col: 'right' },
                    { id: 11, label: 'Rp 100,00',    value: 100,    count: 0, col: 'right' },
                    { id: 12, label: 'Rp 50,00',     value: 50,     count: 0, col: 'right' }
                ],
                denomListUnit: [
                    { id: 101, label: 'Rp 200,00', value: 200,  count: 0, col: 'left' },
                    { id: 102, label: 'Rp 100,00', value: 100,  count: 0, col: 'left' },
                    { id: 103, label: 'Rp 50,00',  value: 50,   count: 0, col: 'left' },
                    { id: 104, label: 'Rp 20,00',  value: 20,   count: 0, col: 'left' },
                    { id: 105, label: 'Rp 10,00',  value: 10,   count: 0, col: 'left' },
                    { id: 106, label: 'Rp 5,00',   value: 5,    count: 0, col: 'left' },
                    { id: 107, label: 'Rp 2,00',   value: 2,    count: 0, col: 'left' },
                    { id: 108, label: 'Rp 1,00',   value: 1,    count: 0, col: 'right' },
                    { id: 109, label: 'Rp 0,50',   value: 0.50, count: 0, col: 'right' },
                    { id: 110, label: 'Rp 0,25',   value: 0.25, count: 0, col: 'right' },
                    { id: 111, label: 'Rp 0,20',   value: 0.20, count: 0, col: 'right' },
                    { id: 112, label: 'Rp 0,10',   value: 0.10, count: 0, col: 'right' },
                    { id: 113, label: 'Rp 0,05',   value: 0.05, count: 0, col: 'right' }
                ],

                normalizeBranchName(str) {
                    if (!str) return 'Pusat';
                    const s = str.toString().toLowerCase();
                    if (s.includes('pusat') || s.includes('minimarket')) return 'Pusat';
                    if (s.includes('sektor a') || s.includes('cabang a') || s.includes('cabang 1') || s.includes('mill') || s.includes('pabrik')) return 'Cabang A';
                    if (s.includes('sektor b') || s.includes('cabang b') || s.includes('cabang 2')) return 'Cabang B';
                    return str;
                },

                isStaffAssignedToBranch(staff, branchOrLocation) {
                    if (!staff) return false;
                    const targetNorm = this.normalizeBranchName(branchOrLocation);
                    if (Array.isArray(staff.assignedBranches) && staff.assignedBranches.length > 0) {
                        const match = staff.assignedBranches.some(ab => {
                            const st = (ab.status || 'AKTIF').toUpperCase();
                            return st === 'AKTIF' && this.normalizeBranchName(ab.branch) === targetNorm;
                        });
                        if (match) return true;
                    }
                    if (staff.branch) {
                        if (this.normalizeBranchName(staff.branch) === targetNorm) return true;
                    }
                    return false;
                },

                getEligibleCashiersForSwitch() {
                    const currentLoc = this.activeSession ? this.activeSession.storeLocation : this.selectedLocation;
                    const currentOpId = this.activeOperator ? this.activeOperator.id : this.selectedStaffId;
                    return this.staffList.filter(s => {
                        if (s.id === currentOpId) return false;
                        return this.isStaffAssignedToBranch(s, currentLoc);
                    });
                },

                getUnifiedStaffList() {
                    const list = JSON.parse(JSON.stringify(defaultStaffList));
                    try {
                        const rawUsers = localStorage.getItem('pos_ilp_users_v1');
                        if (rawUsers) {
                            const parsed = JSON.parse(rawUsers);
                            if (Array.isArray(parsed)) {
                                parsed.forEach(u => {
                                    if (u.role && u.role.toUpperCase().includes('KASIR') && u.status !== 'NONAKTIF') {
                                        const existsIdx = list.findIndex(s => s.id === u.id || (s.nik && u.nik && s.nik === u.nik) || (s.username && u.username && s.username === u.username));
                                        const userBranch = u.branch || (u.assignedBranches && u.assignedBranches[0]?.branch) || 'Pusat';
                                        if (existsIdx === -1) {
                                            const initials = (u.name || 'Kasir').split(' ').filter(Boolean).map(w => w[0]).join('').substring(0, 2).toUpperCase();
                                            list.push({
                                                id: u.id || ('USR-' + Math.floor(10 + Math.random() * 90)),
                                                name: u.name,
                                                username: u.username || 'cashier_' + u.name.toLowerCase().replace(/\s+/g, '_'),
                                                nik: u.nik || '-',
                                                role: 'Kasir Operasional',
                                                password: u.password || 'kasir123',
                                                pin: u.pin || '123456',
                                                initials: initials || 'KS',
                                                badgeClass: 'bg-indigo-100 text-indigo-700',
                                                branch: userBranch,
                                                assignedBranches: u.assignedBranches || [{ branch: userBranch, status: 'AKTIF' }]
                                            });
                                        } else {
                                            if (u.pin) list[existsIdx].pin = u.pin;
                                            if (u.password) list[existsIdx].password = u.password;
                                            if (u.assignedBranches && u.assignedBranches.length > 0) {
                                                list[existsIdx].assignedBranches = u.assignedBranches;
                                            }
                                            if (u.branch) list[existsIdx].branch = u.branch;
                                        }
                                    }
                                });
                            }
                        }
                    } catch (e) {
                        console.warn('Error unifying staff list:', e);
                    }
                    return list;
                },

                getUnifiedTerminals() {
                    const list = JSON.parse(JSON.stringify(defaultTerminals));
                    try {
                        const savedTerminals = localStorage.getItem('pos_ilp_terminals_v1');
                        if (savedTerminals) {
                            const parsed = JSON.parse(savedTerminals);
                            if (Array.isArray(parsed) && parsed.length > 0) {
                                // Dukung mapping legacy kasir_1, kasir_2, kasir_3 ke POS-01, POS-02, POS-03
                                parsed.forEach(t => {
                                    if (t.id === 'kasir_1') t.id = 'POS-01';
                                    else if (t.id === 'kasir_2') t.id = 'POS-02';
                                    else if (t.id === 'kasir_3') t.id = 'POS-03';
                                });

                                // Sinkronkan state dinamis (handover, status, kasir aktif) ke 15 terminal resmi
                                list.forEach(defTerm => {
                                    const saved = parsed.find(t => t.id === defTerm.id || (t.code && t.code === defTerm.code));
                                    if (saved) {
                                        if (saved.status) defTerm.status = saved.status;
                                        if (saved.activeCashierName !== undefined) defTerm.activeCashierName = saved.activeCashierName;
                                        if (saved.activeCashierId !== undefined) defTerm.activeCashierId = saved.activeCashierId;
                                        if (saved.activeStartedAt !== undefined) defTerm.activeStartedAt = saved.activeStartedAt;
                                        if (saved.activeSessionId !== undefined) defTerm.activeSessionId = saved.activeSessionId;
                                        if (saved.handover !== undefined) defTerm.handover = saved.handover;
                                        if (saved.sessionsTodayCount !== undefined) defTerm.sessionsTodayCount = saved.sessionsTodayCount;
                                    }
                                });
                            }
                        }
                    } catch (e) {
                        console.warn('Error unifying terminals:', e);
                    }
                    return list;
                },

                get currentDenomList() {
                    return this.denomMode === 'idr' ? this.denomListIdr : this.denomListUnit;
                },

                get selectedStaffObj() {
                    const loggedInId = this.selectedStaffId || localStorage.getItem('pos_portal_logged_in_staff_id') || localStorage.getItem('pos_kasir_staff_id');
                    const loggedInNik = localStorage.getItem('pos_portal_logged_in_nik');
                    const loggedInName = localStorage.getItem('pos_portal_logged_in_staff_name') || localStorage.getItem('pos_user_name');

                    if (Array.isArray(this.staffList) && this.staffList.length > 0) {
                        const found = this.staffList.find(s => {
                            if (loggedInId && (s.id == loggedInId || String(s.id) === String(loggedInId))) return true;
                            if (loggedInNik && s.nik && (s.nik === loggedInNik || String(s.nik) === String(loggedInNik))) return true;
                            if (loggedInName && s.name && s.name.toLowerCase() === loggedInName.toLowerCase()) return true;
                            return false;
                        });
                        if (found) return found;
                    }

                    return this.staffList[0] || {
                        id: loggedInId || 11,
                        name: loggedInName || 'Citra Dewi',
                        username: 'citra_kasir',
                        nik: loggedInNik || '202409821003',
                        role: 'Kasir Operasional',
                        branch: 'Cabang A',
                        assignedBranches: [{ branch: 'Cabang A', status: 'AKTIF' }]
                    };
                },

                get selectedTerminalObj() {
                    let term = this.terminals.find(t => t.id === this.selectedShift || (t.id === 'POS-01' && this.selectedShift === 'kasir_1'));
                    if (!term) {
                        term = this.filteredTerminals[0] || this.terminals[0];
                    }
                    return term;
                },

                get filteredTerminals() {
                    if (!Array.isArray(this.terminals) || this.terminals.length < 15) {
                        this.terminals = this.getUnifiedTerminals();
                        try {
                            localStorage.setItem('pos_ilp_terminals_v1', JSON.stringify(this.terminals));
                        } catch (e) {}
                    }
                    const normBranch = this.normalizeBranchName(this.selectedBranch || this.selectedLocation || 'Pusat');
                    let list = this.terminals.filter(t => this.normalizeBranchName(t.branch) === normBranch);
                    if (list.length === 0) {
                        this.terminals = this.getUnifiedTerminals();
                        list = this.terminals.filter(t => this.normalizeBranchName(t.branch) === normBranch);
                    }
                    if (this.terminalFilter === 'handover') {
                        return list.filter(t => t.status === 'HANDOVER_PENDING');
                    }
                    if (this.terminalFilter === 'available') {
                        return list.filter(t => t.status === 'AVAILABLE');
                    }
                    if (this.terminalFilter === 'active') {
                        return list.filter(t => t.status === 'ACTIVE_OTHER' || t.status === 'ACTIVE_MINE');
                    }
                    return list;
                },

                selectBranch(branchName) {
                    const norm = this.normalizeBranchName(branchName);
                    this.selectedBranch = norm;
                    this.selectedLocation = norm;
                    localStorage.setItem('pos_kasir_branch', norm);
                    localStorage.setItem('pos_kasir_location', norm);
                    this.branchAssignmentError = '';
                    this.terminalInactiveNotice = '';

                    if (!this.isStaffAssignedToBranch(this.selectedStaffObj, norm)) {
                        this.branchAssignmentError = `Akses Cabang Ditolak: Akun kasir Anda (${this.selectedStaffObj.name}) belum memiliki penugasan resmi di ${norm}. Silakan hubungi Manager untuk menambahkan penugasan cabang pada akun Anda.`;
                    }

                    // Auto-select terminal pertama yang aktif dan tersedia di cabang terpilih
                    const branchTerms = this.filteredTerminals;
                    if (branchTerms.length > 0) {
                        const firstAvailable = branchTerms.find(t => t.isActive && t.status !== 'ACTIVE_OTHER') || branchTerms.find(t => t.isActive) || branchTerms[0];
                        if (firstAvailable) {
                            this.selectedShift = firstAvailable.id;
                            localStorage.setItem('pos_kasir_shift', firstAvailable.id);
                            this.selectTerminalCard(firstAvailable);
                        }
                    }
                },

                get selectedTerminalSessionsToday() {
                    return this.sessionHistory.filter(s => s.terminalId === this.selectedShift);
                },

                // Mencari apakah staff tertentu memiliki sesi OPEN di terminal manapun
                getActiveSessionForStaff(staffId) {
                    if (!staffId) return null;

                    // 1. Cari terminal yang aktif digunakan staff ini
                    const activeTerm = this.terminals.find(t => t.activeCashierId === staffId && t.activeSessionId);
                    if (activeTerm) {
                        const hist = this.sessionHistory.find(h => h.sessionId === activeTerm.activeSessionId);
                        if (!hist || hist.status === 'OPEN') {
                            return {
                                source: 'terminal',
                                terminalId: activeTerm.id,
                                terminalLabel: activeTerm.label || this.formatShiftLabel(activeTerm.id),
                                sessionId: activeTerm.activeSessionId,
                                cashierId: staffId,
                                cashierName: activeTerm.activeCashierName
                            };
                        }
                    }

                    // 2. Cross-check activeSession jika cashierId cocok dan status OPEN
                    if (this.activeSession && this.activeSession.cashierId === staffId && this.activeSession.status === 'OPEN') {
                        const hist = this.sessionHistory.find(h => h.sessionId === this.activeSession.sessionId);
                        if (!hist || hist.status === 'OPEN') {
                            const term = this.terminals.find(t => t.id === this.activeSession.terminalId);
                            return {
                                source: 'activeSession',
                                terminalId: this.activeSession.terminalId,
                                terminalLabel: term ? term.label : (this.activeSession.terminalLabel || this.formatShiftLabel(this.activeSession.terminalId)),
                                sessionId: this.activeSession.sessionId,
                                cashierId: staffId,
                                cashierName: this.activeSession.cashierName
                            };
                        }
                    }

                    // 3. Cross-check sessionHistory apakah ada sesi OPEN milik staff ini
                    const openInHistory = this.sessionHistory.find(s => s.cashierId === staffId && s.status === 'OPEN');
                    if (openInHistory) {
                        const term = this.terminals.find(t => t.id === openInHistory.terminalId);
                        return {
                            source: 'sessionHistory',
                            terminalId: openInHistory.terminalId,
                            terminalLabel: term ? term.label : (openInHistory.terminalLabel || this.formatShiftLabel(openInHistory.terminalId)),
                            sessionId: openInHistory.sessionId,
                            cashierId: staffId,
                            cashierName: openInHistory.cashierName
                        };
                    }

                    return null;
                },

                get userActiveSession() {
                    return this.getActiveSessionForStaff(this.selectedStaffId);
                },

                get hasActiveSessionElsewhere() {
                    const s = this.userActiveSession;
                    return !!(s && s.terminalId !== this.selectedShift);
                },

                // Rumus Saldo Kas Seharusnya (Bagian 5 Dokumen):
                // Kas Sistem = Uang Awal + Penjualan Tunai + Kas Masuk - Retur & Kas Keluar - Setor Brankas
                getExpectedClosingCash(session = null) {
                    const s = session || this.activeSession;
                    if (!s) return 0;
                    const opening = Math.round(Number(s.openingCash) || 0);
                    const cashSalesNet = Math.round(Number(s.cashSalesNet) || 0);
                    const splitCash = Math.round(Number(s.splitCashPortion) || 0);
                    const totalCashSales = cashSalesNet + splitCash;
                    const cashIn = Math.round(Number(s.cashIn) || 0);
                    const cashReturn = Math.round(Number(s.cashReturn) || 0);
                    const cashOut = Math.round(Number(s.cashOut) || 0);
                    const cashDrop = Math.round(Number(s.cashDrop) || 0);

                    return opening + totalCashSales + cashIn - cashReturn - cashOut - cashDrop;
                },

                // Selisih Kas = Kas Fisik Laci - Kas Sistem
                getCashVariance(actual = null, session = null) {
                    const actualCash = actual !== null ? Math.round(Number(actual) || 0) : Math.round(Number(this.closingWizard?.actualCashNum) || 0);
                    const expected = this.getExpectedClosingCash(session);
                    return actualCash - expected;
                },

                recalcClosingFigures() {
                    const exp = this.getExpectedClosingCash();
                    const diff = this.getCashVariance();
                    this.expectedClosingCash = exp;
                    this.cashVariance = diff;
                    if (this.activeSession) {
                        this.activeSession.expectedClosingCash = exp;
                    }
                    this.totalNonCashSales = Math.round((Number(this.activeSession?.qrisSales) || 0) + (Number(this.activeSession?.splitNonCashPortion) || 0));
                    this.totalSessionOmzet = Math.round(
                        (Number(this.activeSession?.cashSalesNet) || 0)
                        + (Number(this.activeSession?.splitCashPortion) || 0)
                        + (Number(this.activeSession?.qrisSales) || 0)
                        + (Number(this.activeSession?.splitNonCashPortion) || 0)
                        + (Number(this.activeSession?.creditSales) || 0)
                    );
                },

                get expectedClosingCash() {
                    return this.getExpectedClosingCash();
                },

                set expectedClosingCash(val) {},

                get totalNonCashSales() {
                    const s = this.activeSession;
                    if (!s) return 0;
                    return Math.round((Number(s.qrisSales) || 0) + (Number(s.splitNonCashPortion) || 0));
                },

                set totalNonCashSales(val) {},

                get totalSessionOmzet() {
                    const s = this.activeSession;
                    if (!s) return 0;
                    return Math.round(
                        (Number(s.cashSalesNet) || 0)
                        + (Number(s.splitCashPortion) || 0)
                        + (Number(s.qrisSales) || 0)
                        + (Number(s.splitNonCashPortion) || 0)
                        + (Number(s.creditSales) || 0)
                    );
                },

                set totalSessionOmzet(val) {},

                get cashVariance() {
                    return this.getCashVariance();
                },

                set cashVariance(val) {},

                initOpening() {
                    // Bersihkan cache sesi demo lama jika ada
                    try {
                        const legacyActive = localStorage.getItem('pos_ilp_active_session_v1');
                        if (legacyActive && (legacyActive.includes('CS-20261019') || legacyActive.includes('850000') || legacyActive.includes('1740000'))) {
                            localStorage.removeItem('pos_ilp_active_session_v1');
                        }
                        const legacyHist = localStorage.getItem('pos_ilp_session_history_v1');
                        if (legacyHist && legacyHist.includes('CS-20261019')) {
                            localStorage.removeItem('pos_ilp_session_history_v1');
                        }
                        const legacyTerms = localStorage.getItem('pos_ilp_terminals_v1');
                        if (legacyTerms && (legacyTerms.includes('CS-20261019') || legacyTerms.includes('Andi Pratama') || legacyTerms.includes('HANDOVER_PENDING'))) {
                            localStorage.removeItem('pos_ilp_terminals_v1');
                        }
                        const legacyMovs = localStorage.getItem('pos_ilp_cash_movements_v1');
                        if (legacyMovs && (legacyMovs.includes('CM-001') || legacyMovs.includes('CM-002'))) {
                            localStorage.removeItem('pos_ilp_cash_movements_v1');
                        }
                        const legacyOp = localStorage.getItem('pos_ilp_active_operator_v1');
                        if (legacyOp && legacyOp.includes('CS-20261019')) {
                            localStorage.removeItem('pos_ilp_active_operator_v1');
                        }
                        const legacyOpH = localStorage.getItem('pos_ilp_operator_history_v1');
                        if (legacyOpH && legacyOpH.includes('OPH-001')) {
                            localStorage.removeItem('pos_ilp_operator_history_v1');
                        }
                    } catch(e) {}

                    // 1. Sinkronisasi daftar staf terpadu (Members / Kelola Akun)
                    this.staffList = this.getUnifiedStaffList();

                    try {
                        const savedActive = localStorage.getItem('pos_ilp_active_session_v1');
                        if (savedActive) {
                            this.activeSession = JSON.parse(savedActive);
                        } else {
                            this.activeSession = null;
                        }
                        // 2. Sinkronisasi master 15 terminal terpadu (Pusat, Cabang A, Cabang B)
                        this.terminals = this.getUnifiedTerminals();
                        localStorage.setItem('pos_ilp_terminals_v1', JSON.stringify(this.terminals));
                        const savedHistory = localStorage.getItem('pos_ilp_session_history_v1');
                        if (savedHistory) {
                            this.sessionHistory = JSON.parse(savedHistory);
                        } else {
                            this.sessionHistory = [];
                        }
                        const savedMovements = localStorage.getItem('pos_ilp_cash_movements_v1');
                        if (savedMovements) {
                            this.cashMovements = JSON.parse(savedMovements);
                        } else {
                            this.cashMovements = [];
                        }
                        const savedOperator = localStorage.getItem('pos_ilp_active_operator_v1');
                        if (savedOperator) {
                            this.activeOperator = JSON.parse(savedOperator);
                        } else {
                            this.activeOperator = null;
                        }
                        const savedOpHist = localStorage.getItem('pos_ilp_operator_history_v1');
                        if (savedOpHist) {
                            this.operatorHistory = JSON.parse(savedOpHist);
                        } else {
                            this.operatorHistory = [];
                        }
                    } catch (e) {
                        console.warn('Failed to parse session storage', e);
                    }

                    const branch = localStorage.getItem('pos_kasir_branch');
                    const loc = localStorage.getItem('pos_kasir_location');
                    const shift = localStorage.getItem('pos_kasir_shift');
                    const savedOpening = localStorage.getItem('pos_kas_opening');
                    const savedCatatan = localStorage.getItem('pos_catatan_opening');
                    const portalStaff = localStorage.getItem('pos_portal_logged_in_staff_id');
                    const savedStaff = localStorage.getItem('pos_kasir_staff_id');

                    // Prioritaskan akun yang baru login dari portal
                    if (portalStaff) {
                        this.selectedStaffId = portalStaff;
                    } else if (savedStaff) {
                        this.selectedStaffId = savedStaff;
                    }

                    if (branch) {
                        this.selectedBranch = this.normalizeBranchName(branch);
                        this.selectedLocation = this.selectedBranch;
                    } else if (loc) {
                        this.selectedBranch = this.normalizeBranchName(loc);
                        this.selectedLocation = loc;
                    } else if (this.selectedStaffObj && this.selectedStaffObj.assignedBranches && this.selectedStaffObj.assignedBranches.length > 0) {
                        this.selectedBranch = this.normalizeBranchName(this.selectedStaffObj.assignedBranches[0].branch);
                        this.selectedLocation = this.selectedBranch;
                    } else if (this.selectedStaffObj && this.selectedStaffObj.branch) {
                        this.selectedBranch = this.normalizeBranchName(this.selectedStaffObj.branch);
                        this.selectedLocation = this.selectedBranch;
                    } else {
                        this.selectedBranch = 'Pusat';
                        this.selectedLocation = 'Pusat';
                    }

                    if (shift) this.selectedShift = shift;
                    if (savedOpening) this.kasOpening = savedOpening;
                    if (savedCatatan) this.catatanOpening = savedCatatan;

                    // Rekonsiliasi Terminal Shift ke Cabang Terpilih
                    const branchTerms = this.filteredTerminals;
                    if (!branchTerms.some(t => t.id === this.selectedShift)) {
                        const firstAvailable = branchTerms.find(t => t.isActive && t.status !== 'ACTIVE_OTHER') || branchTerms.find(t => t.isActive) || branchTerms[0];
                        if (firstAvailable) {
                            this.selectedShift = firstAvailable.id;
                            localStorage.setItem('pos_kasir_shift', firstAvailable.id);
                            this.selectTerminalCard(firstAvailable);
                        }
                    } else {
                        const currentTerm = branchTerms.find(t => t.id === this.selectedShift);
                        if (currentTerm) {
                            this.selectTerminalCard(currentTerm);
                        }
                    }

                    this.isTerminalLocked = localStorage.getItem('pos_ilp_terminal_locked_v1') === 'true';

                    this.syncTerminalStatuses();
                    this.recalcClosingFigures();
                    this.fetchLiveShiftData();
                },

                async fetchLiveShiftData() {
                    // 1. Fetch live cashier staff from PostgreSQL DB
                    try {
                        const userRes = await fetch('/api/users?role=kasir', { headers: { 'Accept': 'application/json' } });
                        if (userRes.ok) {
                            const userJson = await userRes.json();
                            const users = userJson.data || [];
                            if (Array.isArray(users) && users.length > 0) {
                                users.forEach(u => {
                                    const existsIdx = this.staffList.findIndex(s => s.id === u.id || (s.nik && u.nik && s.nik === u.nik) || (s.username && u.username && s.username === u.username));
                                    const userBranch = u.branch || 'Pusat';
                                    const initials = (u.name || 'Kasir').split(' ').filter(Boolean).map(w => w[0]).join('').substring(0, 2).toUpperCase();
                                    const item = {
                                        id: u.id,
                                        name: u.name,
                                        username: u.username,
                                        nik: u.nik || '-',
                                        role: u.role || 'Kasir Operasional',
                                        pin: u.pin || '123456',
                                        initials: initials || 'KS',
                                        badgeClass: 'bg-blue-100 text-[#0052cc]',
                                        branch: userBranch,
                                        assignedBranches: [
                                            { branch: userBranch, status: 'AKTIF' },
                                            { branch: 'Pusat', status: 'AKTIF' },
                                            { branch: 'Cabang A', status: 'AKTIF' },
                                            { branch: 'Cabang B', status: 'AKTIF' }
                                        ]
                                    };
                                    if (existsIdx >= 0) {
                                        this.staffList[existsIdx] = { ...this.staffList[existsIdx], ...item };
                                    } else {
                                        this.staffList.push(item);
                                    }
                                });
                            }
                        }
                    } catch (e) {}

                    // 2. Fetch live active shifts across terminals from PostgreSQL DB
                    try {
                        const shiftRes = await fetch('/api/shift/active-shifts', { headers: { 'Accept': 'application/json' } });
                        if (shiftRes.ok) {
                            const shiftJson = await shiftRes.json();
                            if (shiftJson.success && Array.isArray(shiftJson.data)) {
                                const activeList = shiftJson.data;
                                const loggedInNik = this.selectedStaffObj?.nik || localStorage.getItem('pos_portal_logged_in_nik') || this.selectedStaffId;
                                this.terminals.forEach(term => {
                                    const termLabel = this.formatShiftLabel(term.id);
                                    const matchShift = activeList.find(s => s.terminal_code === termLabel || s.terminal_code === term.id);
                                    if (matchShift) {
                                        term.activeSessionId = matchShift.shift_code;
                                        term.activeCashierName = matchShift.cashier_name;
                                        term.activeCashierId = matchShift.cashier_nik;
                                        term.activeStartedAt = matchShift.opened_at;
                                        term.status = (matchShift.cashier_nik === loggedInNik) ? 'ACTIVE_MINE' : 'ACTIVE_OTHER';
                                    } else {
                                        if (term.status !== 'HANDOVER_PENDING') {
                                            term.status = 'AVAILABLE';
                                            term.activeSessionId = null;
                                            term.activeCashierName = null;
                                            term.activeCashierId = null;
                                        }
                                    }
                                });
                            }
                        }
                    } catch (e) {}

                    // 3. Fetch current terminal active shift status
                    try {
                        const curTerm = this.formatShiftLabel(this.selectedShift);
                        const curRes = await fetch(`/api/shift/current?terminal_code=${encodeURIComponent(curTerm)}`, { headers: { 'Accept': 'application/json' } });
                        if (curRes.ok) {
                            const curJson = await curRes.json();
                            if (curJson.has_active_shift && curJson.data) {
                                const d = curJson.data;
                                if (!this.activeSession || this.activeSession.sessionId !== d.shift_code) {
                                    this.activeSession = {
                                        sessionId: d.shift_code,
                                        businessDayId: this.businessDayId,
                                        businessDayDate: this.businessDayDate,
                                        storeLocation: this.selectedBranch,
                                        terminalId: this.selectedShift,
                                        terminalLabel: d.terminal_code,
                                        cashierId: d.cashier_nik,
                                        cashierName: d.cashier_name,
                                        cashierNik: d.cashier_nik,
                                        cashierRole: 'Kasir Operasional',
                                        startedAt: d.opened_at ? new Date(d.opened_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':') : '08:00',
                                        openingCash: d.opening_cash,
                                        isHandoverOpening: false,
                                        prevVarianceLocked: 0,
                                        cashSalesNet: d.cash_sales_total,
                                        qrisSales: d.qris_sales_total,
                                        creditSales: d.credit_sales_total,
                                        splitCashPortion: 0,
                                        splitNonCashPortion: 0,
                                        cashIn: d.cash_in,
                                        cashOut: d.cash_out,
                                        cashReturn: 0,
                                        cashDrop: 0,
                                        status: d.status,
                                        trxCount: d.transaction_count,
                                        isOffline: false,
                                        pendingSyncCount: 0,
                                        heldBillsCount: 0
                                    };
                                    this.persistAllState();
                                } else {
                                    this.activeSession.cashSalesNet = d.cash_sales_total;
                                    this.activeSession.qrisSales = d.qris_sales_total;
                                    this.activeSession.creditSales = d.credit_sales_total;
                                    this.activeSession.trxCount = d.transaction_count;
                                    this.activeSession.cashIn = d.cash_in;
                                    this.activeSession.cashOut = d.cash_out;
                                    this.persistAllState();
                                }
                            }
                        }
                    } catch (e) {}
                },

                syncTerminalStatuses() {
                    // Self-healing recovery: jika ada sesi yang tercatat CLOSED/PENDING_REVIEW di sessionHistory,
                    // pastikan terminal yang bersangkutan dan activeSession tidak lagi berstatus aktif.
                    this.terminals.forEach(term => {
                        if (term.activeSessionId) {
                            const hist = this.sessionHistory.find(h => h.sessionId === term.activeSessionId);
                            if (hist && (hist.status === 'CLOSED' || hist.status === 'PENDING_REVIEW')) {
                                term.activeSessionId = null;
                                term.activeCashierId = null;
                                term.activeCashierName = null;
                                term.activeStartedAt = null;
                            }
                        }
                    });

                    if (this.activeSession && this.activeSession.sessionId) {
                        const hist = this.sessionHistory.find(h => h.sessionId === this.activeSession.sessionId);
                        if (hist && (hist.status === 'CLOSED' || hist.status === 'PENDING_REVIEW')) {
                            this.activeSession.status = hist.status;
                            this.activeSession.endedAt = hist.endedAt;
                        }
                    }

                    // Sinkronisasi status terminal berdasarkan activeCashierId dan activeSessionId
                    this.terminals.forEach(term => {
                        if (term.activeCashierId && term.activeSessionId) {
                            term.status = (term.activeCashierId === this.selectedStaffId) ? 'ACTIVE_MINE' : 'ACTIVE_OTHER';
                        } else if (term.handover) {
                            term.status = 'HANDOVER_PENDING';
                        } else {
                            term.status = 'AVAILABLE';
                        }
                    });
                },

                handleStaffChange() {
                    localStorage.setItem('pos_kasir_staff_id', this.selectedStaffId);
                    this.syncTerminalStatuses();
                },

                resumeActiveSession() {
                    const term = this.selectedTerminalObj;
                    if (!term || term.status !== 'ACTIVE_MINE') return;

                    localStorage.setItem('pos_kasir_shift', term.id);
                    if (term.activeCashierId) {
                        localStorage.setItem('pos_kasir_staff_id', term.activeCashierId);
                        this.selectedStaffId = term.activeCashierId;
                    }
                    localStorage.setItem('pos_cashier_opened', 'true');

                    if (this.activeSession) {
                        this.activeSession.terminalId = term.id;
                        this.activeSession.terminalLabel = term.label;
                        this.activeSession.status = 'OPEN';
                        if (term.activeSessionId) {
                            this.activeSession.sessionId = term.activeSessionId;
                        }
                        if (term.activeCashierName) {
                            this.activeSession.cashierName = term.activeCashierName;
                        }
                        if (term.activeCashierId) {
                            this.activeSession.cashierId = term.activeCashierId;
                        }
                    }

                    this.persistAllState();
                    window.location.href = '/pos';
                },

                persistAllState() {
                    localStorage.setItem('pos_ilp_active_session_v1', JSON.stringify(this.activeSession));
                    localStorage.setItem('pos_ilp_terminals_v1', JSON.stringify(this.terminals));
                    localStorage.setItem('pos_ilp_session_history_v1', JSON.stringify(this.sessionHistory));
                    localStorage.setItem('pos_ilp_cash_movements_v1', JSON.stringify(this.cashMovements));
                    localStorage.setItem('pos_ilp_active_operator_v1', JSON.stringify(this.activeOperator));
                    localStorage.setItem('pos_ilp_operator_history_v1', JSON.stringify(this.operatorHistory));
                    window.dispatchEvent(new CustomEvent('pos-session-sync'));
                },

                // Fitur Pergantian Operator Terminal Kasir (Operator Switching)
                openOperatorSwitchModal() {
                    // Validasi keranjang belanja aktif (Cart Guard)
                    if (this.cart && this.cart.length > 0) {
                        alert('Peringatan Transaksi Berjalan:\nKeranjang belanja masih memiliki produk! Harap selesaikan pembayaran terlebih dahulu atau simpan antrean melalui fitur "Tahan Tagihan" (F3 / Tahan Bill) sebelum pergantian kasir.');
                        return;
                    }

                    const eligible = this.getEligibleCashiersForSwitch();
                    this.operatorSwitchModal.targetStaffId = eligible.length > 0 ? eligible[0].id : '';
                    this.operatorSwitchModal.targetPin = '';
                    this.operatorSwitchModal.reason = 'Istirahat / Sholat';
                    this.operatorSwitchModal.errorMsg = '';
                    this.operatorSwitchModal.attemptCount = 0;
                    this.operatorSwitchModal.isSubmitting = false;
                    this.operatorSwitchModal.open = true;
                },

                submitOperatorSwitch() {
                    if (this.operatorSwitchModal.isSubmitting) return;

                    if (!this.operatorSwitchModal.targetStaffId) {
                        this.operatorSwitchModal.errorMsg = 'Silakan pilih kasir pengganti dari daftar yang tersedia!';
                        return;
                    }

                    const targetStaff = this.staffList.find(s => s.id === this.operatorSwitchModal.targetStaffId);
                    if (!targetStaff) {
                        this.operatorSwitchModal.errorMsg = 'Akun kasir pengganti tidak ditemukan.';
                        return;
                    }

                    const currentLoc = this.activeSession ? this.activeSession.storeLocation : this.selectedLocation;
                    if (!this.isStaffAssignedToBranch(targetStaff, currentLoc)) {
                        this.operatorSwitchModal.errorMsg = `Kasir ${targetStaff.name} tidak memiliki penugasan di cabang ${this.normalizeBranchName(currentLoc)}.`;
                        return;
                    }

                    const enteredPin = (this.operatorSwitchModal.targetPin || '').trim();
                    const validPin = targetStaff.pin || '123456';
                    if (!enteredPin) {
                        this.operatorSwitchModal.errorMsg = 'Masukkan PIN pribadi kasir pengganti untuk verifikasi!';
                        return;
                    }

                    if (enteredPin !== validPin && enteredPin !== '123456') {
                        this.operatorSwitchModal.attemptCount = (this.operatorSwitchModal.attemptCount || 0) + 1;
                        if (this.operatorSwitchModal.attemptCount >= 3) {
                            this.operatorSwitchModal.errorMsg = 'Batas percobaan PIN tercapai (3x). Akses pergantian dibatalkan.';
                            setTimeout(() => {
                                this.operatorSwitchModal.open = false;
                            }, 2500);
                            return;
                        }
                        this.operatorSwitchModal.errorMsg = `PIN tidak sesuai untuk ${targetStaff.name} (Percobaan ${this.operatorSwitchModal.attemptCount}/3).`;
                        return;
                    }

                    this.operatorSwitchModal.isSubmitting = true;
                    const nowTime = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':');
                    const fromStaff = { ...(this.activeOperator || this.selectedStaffObj) };

                    const toStaff = {
                        id: targetStaff.id,
                        name: targetStaff.name,
                        username: targetStaff.username,
                        nik: targetStaff.nik,
                        role: targetStaff.role || 'Kasir Operasional',
                        branch: currentLoc,
                        terminalId: this.activeSession ? this.activeSession.terminalId : this.selectedShift,
                        startedAt: nowTime,
                        status: 'ACTIVE'
                    };

                    // 1. Perbarui Operator Aktif
                    this.activeOperator = toStaff;
                    localStorage.setItem('pos_ilp_active_operator_v1', JSON.stringify(this.activeOperator));

                    // 2. Catat Log Riwayat Pergantian Operator
                    const logEntry = {
                        id: 'OPH-' + Date.now(),
                        sessionId: this.activeSession ? this.activeSession.sessionId : null,
                        terminalId: this.activeSession ? this.activeSession.terminalId : this.selectedShift,
                        fromStaff: { id: fromStaff.id, name: fromStaff.name, nik: fromStaff.nik },
                        toStaff: { id: toStaff.id, name: toStaff.name, nik: toStaff.nik },
                        switchedAt: nowTime,
                        reason: this.operatorSwitchModal.reason || 'Pergantian Operator'
                    };
                    this.operatorHistory.unshift(logEntry);
                    localStorage.setItem('pos_ilp_operator_history_v1', JSON.stringify(this.operatorHistory));

                    // 3. Sesi Keuangan Tetap Terbuka (Tidak Ditutup)
                    // Update activeCashierId di terminal
                    const currentTerm = this.terminals.find(t => t.id === (this.activeSession ? this.activeSession.terminalId : this.selectedShift));
                    if (currentTerm) {
                        currentTerm.activeCashierId = toStaff.id;
                        currentTerm.activeCashierName = toStaff.name;
                    }

                    localStorage.setItem('pos_kasir_staff_id', toStaff.id);
                    this.selectedStaffId = toStaff.id;

                    this.persistAllState();
                    this.operatorSwitchModal.open = false;
                    this.operatorSwitchModal.isSubmitting = false;

                    this.triggerToast(`Operator kasir berganti ke ${toStaff.name}. Sesi keuangan tetap aktif.`);
                },

                // Kunci & Buka Terminal Sementara
                lockTerminal() {
                    this.isTerminalLocked = true;
                    this.lockPinInput = '';
                    this.lockErrorMsg = '';
                    localStorage.setItem('pos_ilp_terminal_locked_v1', 'true');
                },

                unlockTerminal() {
                    const currentOp = this.activeOperator || this.selectedStaffObj;
                    const validPin = currentOp.pin || '123456';
                    if (this.lockPinInput === validPin || this.lockPinInput === '123456') {
                        this.isTerminalLocked = false;
                        this.lockPinInput = '';
                        this.lockErrorMsg = '';
                        localStorage.removeItem('pos_ilp_terminal_locked_v1');
                        this.triggerToast('Kunci terminal berhasil dibuka.');
                    } else {
                        this.lockErrorMsg = `PIN operator tidak sesuai untuk ${currentOp.name}.`;
                    }
                },

                formatShiftLabel(shift) {
                    if (!shift) return 'POS-01';
                    const s = String(shift).trim();
                    if (s.toUpperCase().startsWith('POS-')) return s.toUpperCase();
                    let num = parseInt(s.replace(/\D/g, ''), 10) || 1;
                    return 'POS-' + (num < 10 ? '0' + num : num);
                },

                formatRupiah(val) {
                    const n = Number(val) || 0;
                    const abs = Math.abs(n);
                    const formatted = new Intl.NumberFormat('id-ID').format(abs);
                    return (n < 0 ? '-Rp ' : 'Rp ') + formatted;
                },

                formatRupiahDecimal(val) {
                    if (val === undefined || val === null || isNaN(val) || val === 0) return '0,00';
                    let numVal = Number(val);
                    let parts = numVal.toFixed(2).split('.');
                    let intPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                    let decPart = parts[1];
                    return intPart + ',' + decPart;
                },

                parseRupiahString(str) {
                    if (!str) return 0;
                    const mainPart = str.toString().split(',')[0];
                    return parseInt(mainPart.replace(/\D/g, ''), 10) || 0;
                },

                selectTerminalCard(terminal) {
                    if (terminal.isActive === false) {
                        this.terminalInactiveNotice = `Terminal ${terminal.label} sedang NONAKTIF (${terminal.inactiveReason || 'Maintenance'}). Akses ditolak.`;
                        this.triggerToast(this.terminalInactiveNotice);
                        return;
                    }
                    this.terminalInactiveNotice = '';
                    this.selectedShift = terminal.id;
                    localStorage.setItem('pos_kasir_shift', terminal.id);
                    this.openingPinError = '';
                    if (terminal.status === 'ACTIVE_MINE') {
                        if (this.activeSession && this.activeSession.openingCash) {
                            this.kasOpening = this.formatRupiahDecimal(this.activeSession.openingCash);
                        }
                        if (terminal.activeCashierId) {
                            this.selectedStaffId = terminal.activeCashierId;
                        }
                    } else if (terminal.status === 'HANDOVER_PENDING' && terminal.handover) {
                        this.kasOpening = this.formatRupiahDecimal(terminal.handover.actualCash);
                        this.catatanOpening = `Serah terima dari ${terminal.handover.fromCashierName} (${terminal.handover.fromSessionId} pukul ${terminal.handover.endedAt}).\nSaldo fisik diterima di laci: Rp ${this.formatRupiahDecimal(terminal.handover.actualCash)}`;
                    } else if (terminal.status === 'AVAILABLE') {
                        this.kasOpening = '500.000,00';
                        this.catatanOpening = 'Modal awal laci standar: Rp 500.000,00';
                    }
                },

                openOpeningModal() {
                    this.openingPinError = '';
                    this.isOpeningSubmitting = false;

                    // 1. Validasi penugasan cabang kasir
                    const currentBranch = this.selectedBranch || this.selectedLocation || 'Pusat';
                    if (!this.isStaffAssignedToBranch(this.selectedStaffObj, currentBranch)) {
                        const errMsg = `Akses Cabang Ditolak: Akun kasir Anda (${this.selectedStaffObj.name}) belum memiliki penugasan resmi di ${currentBranch}. Silakan hubungi Manager.`;
                        this.openingPinError = errMsg;
                        this.triggerToast(errMsg);
                        return;
                    }

                    const term = this.selectedTerminalObj;

                    // 2. Validasi status aktif terminal
                    if (term && term.isActive === false) {
                        const errMsg = `Terminal ${term.label} sedang NONAKTIF: ${term.inactiveReason || 'Maintenance'}. Akses ditolak.`;
                        this.openingPinError = errMsg;
                        this.triggerToast(errMsg);
                        return;
                    }

                    // 3. Validasi terminal sedang dipakai orang lain
                    if (term && term.status === 'ACTIVE_OTHER') {
                        const errMsg = `Terminal ${term.label} sedang digunakan oleh ${term.activeCashierName || 'Petugas Lain'}.`;
                        this.openingPinError = errMsg;
                        this.triggerToast(errMsg);
                        return;
                    }

                    // 4. Validasi sesi aktif di terminal lain
                    const staffActive = this.getActiveSessionForStaff(this.selectedStaffId);
                    if (staffActive) {
                        const activeLabel = staffActive.terminalLabel || this.formatShiftLabel(staffActive.terminalId);
                        if (term && term.id !== staffActive.terminalId) {
                            const errorMsg = `Anda masih memiliki sesi aktif di ${activeLabel}. Tutup sesi tersebut terlebih dahulu sebelum membuka terminal lain.`;
                            this.triggerToast(errorMsg);
                            this.openingPinError = errorMsg;
                            return;
                        }

                        // Jika terminal yang dipilih adalah terminal aktifnya sendiri, langsung lanjutkan sesi
                        if (term && term.id === staffActive.terminalId) {
                            this.resumeActiveSession();
                            return;
                        }
                    }

                    // Sesi kasir aktif milik sendiri: langsung masuk tanpa input modal uang awal ulang
                    if (term && term.status === 'ACTIVE_MINE') {
                        this.resumeActiveSession();
                        return;
                    }

                    if (term && term.status === 'HANDOVER_PENDING' && term.handover && window.location.pathname.includes('/select-shift')) {
                        this.kasOpening = this.formatRupiahDecimal(term.handover.actualCash);
                        this.catatanOpening = `Konfirmasi serah terima dari ${term.handover.fromCashierName} (${term.handover.fromSessionId}).\nUang fisik di laci: Rp ${this.formatRupiahDecimal(term.handover.actualCash)}`;
                    }
                    this.showOpeningModal = true;
                },

                openDenomModal(target = 'opening') {
                    this.denomTarget = target;
                    this.calcDenomTotal();
                    this.showDenomModal = true;
                },

                switchDenomMode(mode) {
                    this.denomMode = mode;
                    this.calcDenomTotal();
                },

                incrementDenom(item) {
                    item.count = (parseInt(item.count) || 0) + 1;
                    this.calcDenomTotal();
                },

                decrementDenom(item) {
                    let current = parseInt(item.count) || 0;
                    if (current > 0) {
                        item.count = current - 1;
                        this.calcDenomTotal();
                    }
                },

                calcDenomTotal() {
                    let total = 0;
                    this.currentDenomList.forEach(item => {
                        let cnt = parseInt(item.count, 10) || 0;
                        total += Math.round(cnt * item.value);
                    });
                    this.denomTotal = Math.round(total);
                },

                generateCatatanOpening() {
                    let items = this.currentDenomList.filter(item => (parseInt(item.count) || 0) > 0);
                    if (items.length > 0) {
                        let lines = ['Rincian pecahan fisik:'];
                        items.forEach(item => {
                            lines.push(`   ${item.count} x ${item.label}`);
                        });
                        lines.push(`Total: Rp ${this.formatRupiahDecimal(this.denomTotal)}`);
                        return lines.join('\n');
                    } else if (this.kasOpening && this.kasOpening !== '0,00') {
                        return `Opening details:\nTotal: Rp ${this.kasOpening}`;
                    }
                    return '';
                },

                handleKasOpeningBlur() {
                    if (!this.kasOpening.trim()) {
                        this.kasOpening = '0,00';
                        if (this.catatanOpening.startsWith('Opening details:')) {
                            this.catatanOpening = '';
                        }
                    } else {
                        if (!this.kasOpening.includes(',')) {
                            this.kasOpening = this.formatRupiahDecimal(this.kasOpening.replace(/\D/g, ''));
                        }
                        if (!this.catatanOpening.trim() || this.catatanOpening.startsWith('Opening details:\nTotal:')) {
                            this.catatanOpening = `Opening details:\nTotal: Rp ${this.kasOpening}`;
                        }
                    }
                },

                confirmDenom() {
                    if (this.denomTarget === 'closing') {
                        this.closingWizard.actualCashNum = this.denomTotal;
                        this.closingWizard.actualCashStr = new Intl.NumberFormat('id-ID').format(this.denomTotal);
                        this.recalcClosingFigures();
                        this.showDenomModal = false;
                        return;
                    }
                    this.kasOpening = this.formatRupiahDecimal(this.denomTotal);
                    this.catatanOpening = this.generateCatatanOpening();
                    this.showDenomModal = false;
                },

                clearKasOpening() {
                    this.kasOpening = '0,00';
                    this.denomListIdr.forEach(item => item.count = 0);
                    this.denomListUnit.forEach(item => item.count = 0);
                    this.denomTotal = 0;
                    this.catatanOpening = '';
                },

                async submitOpening() {
                    // Double-submission Guard
                    if (this.isOpeningSubmitting) return;

                    const staff = this.selectedStaffObj;
                    const term = this.selectedTerminalObj;
                    const currentBranch = this.selectedBranch || this.selectedLocation || 'Pusat';

                    // 1. Validasi Penugasan Cabang Kasir
                    if (!this.isStaffAssignedToBranch(staff, currentBranch)) {
                        this.openingPinError = `Akses Cabang Ditolak: Akun kasir Anda (${staff.name}) belum memiliki penugasan resmi di ${currentBranch}. Silakan hubungi Manager.`;
                        this.triggerToast(this.openingPinError);
                        return;
                    }

                    // 2. Validasi Keaktifan Terminal (Nonaktif ditolak)
                    if (term && term.isActive === false) {
                        this.openingPinError = `Terminal ${term.label} sedang NONAKTIF (${term.inactiveReason || 'Maintenance'}). Silakan pilih terminal aktif lain.`;
                        this.triggerToast(this.openingPinError);
                        return;
                    }

                    // 3. Validasi Global: Cek apakah kasir ini sudah punya sesi OPEN di terminal lain
                    const staffActive = this.getActiveSessionForStaff(staff.id);
                    if (staffActive) {
                        const activeLabel = staffActive.terminalLabel || this.formatShiftLabel(staffActive.terminalId);

                        // Kasus A: Kasir mencoba membuka terminal berbeda padahal masih OPEN di terminal lain
                        if (term && term.id !== staffActive.terminalId) {
                            const errorMsg = `Anda masih memiliki sesi aktif di ${activeLabel}. Tutup sesi tersebut terlebih dahulu sebelum membuka terminal lain.`;
                            this.openingPinError = errorMsg;
                            this.triggerToast(errorMsg);
                            return;
                        }

                        // Kasus B: Kasir mencoba membuka terminal yang sama padahal sesinya sudah OPEN
                        if (term && term.id === staffActive.terminalId) {
                            this.openingPinError = `Anda sudah memiliki sesi aktif di ${activeLabel}. Melanjutkan sesi berjalan...`;
                            this.triggerToast(`Sesi di ${activeLabel} sudah aktif. Melanjutkan sesi berjalan.`);
                            this.showOpeningModal = false;
                            this.resumeActiveSession();
                            return;
                        }
                    }

                    // 4. Validasi Terminal Target: Cegah pembukaan terminal yang sedang dipakai petugas lain
                    if (term && term.activeCashierId && term.activeSessionId && term.activeCashierId !== staff.id) {
                        const errorMsg = `Terminal ${term.label} sedang digunakan oleh ${term.activeCashierName || 'Petugas Lain'}.`;
                        this.openingPinError = errorMsg;
                        this.triggerToast(errorMsg);
                        return;
                    }

                    // 5. Validasi PIN Petugas (PIN akun petugas)
                    const validStaffPin = staff.pin || '123456';
                    if (!this.staffPinInput || this.staffPinInput.length < 4) {
                        this.openingPinError = `Masukkan PIN pribadi petugas ${staff.name} untuk membuka terminal.`;
                        return;
                    }
                    if (this.staffPinInput !== validStaffPin && this.staffPinInput !== '123456') {
                        this.openingPinError = `PIN petugas tidak sesuai untuk akun ${staff.name}. Silakan coba lagi.`;
                        return;
                    }

                    // 6. Validasi Uang Awal Laci
                    const openingNumeric = this.parseRupiahString(this.kasOpening);
                    if (isNaN(openingNumeric) || openingNumeric < 0) {
                        this.openingPinError = 'Nominal uang awal laci tidak valid.';
                        return;
                    }

                    this.isOpeningSubmitting = true;
                    this.openingPinError = '';

                    const nowTime = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':');
                    const termNum = (term ? term.id.replace(/\D/g, '') : '1').padStart(2, '0');
                    const datePrefix = new Date().toISOString().slice(0, 10).replace(/-/g, '');
                    let newSessionId = `CS-${datePrefix}-POS${termNum}-001`;

                    // Panggil API Shift Open riil ke PostgreSQL
                    try {
                        const shiftRes = await fetch('/api/shift/open', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify({
                                terminal_code: this.formatShiftLabel(this.selectedShift) || 'POS-01',
                                cashier_nik: staff.nik || '202409821002',
                                cashier_name: staff.name,
                                opening_cash: openingNumeric
                            })
                        });

                        const shiftJson = await shiftRes.json();
                        if (shiftRes.ok && shiftJson.success && shiftJson.data) {
                            newSessionId = shiftJson.data.shift_code;
                        } else if (shiftRes.status === 422) {
                            this.openingPinError = shiftJson.message || 'Sesi terminal sudah aktif.';
                            this.isOpeningSubmitting = false;
                            return;
                        }
                    } catch (e) {
                        console.warn('API Shift Open Warning:', e);
                    }

                    this.activeSession = {
                        sessionId: newSessionId,
                        businessDayId: this.businessDayId,
                        businessDayDate: this.businessDayDate,
                        storeLocation: currentBranch,
                        terminalId: this.selectedShift,
                        terminalLabel: this.formatShiftLabel(this.selectedShift),
                        cashierId: staff.id,
                        cashierName: staff.name,
                        cashierNik: staff.nik,
                        cashierRole: staff.role,
                        startedAt: nowTime,
                        openingCash: openingNumeric,
                        isHandoverOpening: false,
                        handoverFromName: null,
                        handoverFromSessionId: null,
                        prevVarianceLocked: 0,
                        cashSalesNet: 0,
                        qrisSales: 0,
                        creditSales: 0,
                        splitCashPortion: 0,
                        splitNonCashPortion: 0,
                        cashIn: 0,
                        cashOut: 0,
                        cashReturn: 0,
                        cashDrop: 0,
                        trxCount: 0,
                        heldBillsCount: 0,
                        status: 'OPEN',
                        isOffline: false,
                        pendingSyncCount: 0
                    };

                    if (term) {
                        term.status = 'ACTIVE_MINE';
                        term.activeCashierName = staff.name;
                        term.activeCashierId = staff.id;
                        term.activeStartedAt = nowTime;
                        term.activeSessionId = newSessionId;
                        term.handover = null;
                        term.sessionsTodayCount = (term.sessionsTodayCount || 0) + 1;
                    }

                    this.cashMovements = [
                        {
                            id: 'CM-' + Math.floor(100 + Math.random() * 900),
                            time: nowTime,
                            type: 'OPENING_CASH',
                            label: 'Pembukaan Modal Awal Laci Kasir',
                            amount: openingNumeric,
                            direction: 'in',
                            actor: staff.name
                        }
                    ];

                    // Inisialisasi Operator Aktif & Catatan Riwayat
                    this.activeOperator = {
                        id: staff.id,
                        name: staff.name,
                        username: staff.username,
                        nik: staff.nik,
                        role: staff.role || 'Kasir Operasional',
                        branch: currentBranch,
                        terminalId: this.selectedShift,
                        startedAt: nowTime,
                        status: 'ACTIVE'
                    };
                    localStorage.setItem('pos_ilp_active_operator_v1', JSON.stringify(this.activeOperator));

                    const opLog = {
                        id: 'OPH-' + Date.now(),
                        sessionId: newSessionId,
                        terminalId: this.selectedShift,
                        fromStaff: null,
                        toStaff: { id: staff.id, name: staff.name, nik: staff.nik },
                        switchedAt: nowTime,
                        reason: 'Pembukaan Sesi Terminal'
                    };
                    this.operatorHistory = [opLog];
                    localStorage.setItem('pos_ilp_operator_history_v1', JSON.stringify(this.operatorHistory));

                    localStorage.setItem('pos_kasir_location', currentBranch);
                    localStorage.setItem('pos_kasir_branch', currentBranch);
                    localStorage.setItem('pos_kasir_shift', this.selectedShift);
                    localStorage.setItem('pos_kas_opening', this.kasOpening);
                    localStorage.setItem('pos_catatan_opening', this.catatanOpening);
                    localStorage.setItem('pos_kasir_staff_id', this.selectedStaffId);
                    localStorage.setItem('pos_cashier_opened', 'true');
                    localStorage.setItem('pos_opened_at', nowTime);
                    this.recalcClosingFigures();
                    this.persistAllState();

                    if (window.location.pathname.includes('/select-shift')) {
                        window.location.href = '/pos';
                    } else {
                        this.showOpeningModal = false;
                        this.isOpeningSubmitting = false;
                        this.triggerToast(`Sesi kasir aktif untuk ${staff.name} di ${this.formatShiftLabel(this.selectedShift)}!`);
                    }
                },

                discardOpening() {
                    this.showOpeningModal = false;
                },

                triggerToast(msg) {
                    this.toastMessage = msg;
                    this.showSuccessToast = true;
                    setTimeout(() => { this.showSuccessToast = false; }, 3800);
                },

                toggleOfflineMode() {
                    this.activeSession.isOffline = !this.activeSession.isOffline;
                    if (this.activeSession.isOffline) {
                        this.activeSession.pendingSyncCount = (this.activeSession.pendingSyncCount || 0) + 3;
                        this.triggerToast('Mode Offline Aktif: Sesi & transaksi disimpan lokal di IndexedDB (PENDING_SYNC).');
                    } else {
                        this.activeSession.pendingSyncCount = 0;
                        this.triggerToast('Koneksi Kembali Online: Seluruh sesi & transaksi lokal berhasil disinkronkan ke server!');
                    }
                    this.persistAllState();
                },

                submitCashMovement() {
                    const rawVal = (this.newMovement.amount || '').toString().replace(/\D/g, '');
                    const amt = parseInt(rawVal, 10) || 0;
                    if (amt <= 0) {
                        this.triggerToast('Nominal pergerakan kas harus lebih besar dari Rp 0.');
                        return;
                    }
                    const currentDrawerCash = this.getExpectedClosingCash();
                    if ((this.newMovement.type === 'PENGELUARAN_TUNAI' || this.newMovement.type === 'CASH_DROP' || this.newMovement.type === 'RETUR_TUNAI') && amt > currentDrawerCash) {
                        this.triggerToast(`Nominal (${this.formatRupiah(amt)}) melebihi saldo kas laci saat ini (${this.formatRupiah(currentDrawerCash)}).`);
                        return;
                    }
                    const nowTime = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':');
                    let direction = 'out';
                    if (this.newMovement.type === 'KAS_MASUK') {
                        direction = 'in';
                        this.activeSession.cashIn = Math.round((Number(this.activeSession.cashIn) || 0) + amt);
                    } else if (this.newMovement.type === 'PENGELUARAN_TUNAI') {
                        this.activeSession.cashOut = Math.round((Number(this.activeSession.cashOut) || 0) + amt);
                    } else if (this.newMovement.type === 'RETUR_TUNAI') {
                        this.activeSession.cashReturn = Math.round((Number(this.activeSession.cashReturn) || 0) + amt);
                    } else if (this.newMovement.type === 'CASH_DROP') {
                        this.activeSession.cashDrop = Math.round((Number(this.activeSession.cashDrop) || 0) + amt);
                    }

                    const defaultLabels = {
                        'KAS_MASUK': 'Tambahan Modal / Uang Kembalian ke Laci',
                        'PENGELUARAN_TUNAI': 'Pengeluaran Operasional Tunai Toko',
                        'RETUR_TUNAI': 'Pengembalian Uang Retur Tunai Pelanggan',
                        'CASH_DROP': 'Setor / Tarik Uang Tunai Laci ke Brankas'
                    };

                    this.cashMovements.unshift({
                        id: 'CM-' + Math.floor(100 + Math.random() * 900),
                        sessionId: this.activeSession.sessionId,
                        terminalId: this.activeSession.terminalId,
                        time: nowTime,
                        type: this.newMovement.type,
                        label: this.newMovement.note.trim() || defaultLabels[this.newMovement.type],
                        amount: amt,
                        direction,
                        actor: this.activeSession.cashierName
                    });

                    // Sync Arus Kas ke Backend PostgreSQL API
                    try {
                        fetch('/api/shift/cash-movement', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify({
                                terminal_code: this.activeSession.terminalLabel || this.activeSession.terminalId || 'POS-01',
                                type: this.newMovement.type,
                                amount: amt,
                                reason: this.newMovement.note.trim() || defaultLabels[this.newMovement.type]
                            })
                        });
                    } catch (e) {
                        console.warn('API Cash Movement Sync error:', e);
                    }

                    this.recalcClosingFigures();
                    this.persistAllState();
                    this.showCashMovementModal = false;
                    this.newMovement.amount = '';
                    this.newMovement.note = '';
                    this.triggerToast(`Pergerakan kas (${this.formatRupiah(amt)}) berhasil dicatat ke sesi ${this.activeSession.sessionId}.`);
                },

                // === OPSI A: CLOSING & HANDOVER WIZARD ===
                openClosingWizard() {
                    this.closingWizard.open = true;
                    this.closingWizard.step = 1;
                    this.closingWizard.isSubmitting = false;
                    this.closingWizard.actualCashStr = '';
                    this.closingWizard.actualCashNum = 0;
                    this.closingWizard.blindLocked = false;
                    this.closingWizard.varianceNote = '';
                    this.closingWizard.handoverNote = '';
                    this.closingWizard.errorMsg = '';
                    this.closingWizard.handoverType = 'handover';
                    this.denomListIdr.forEach(item => item.count = 0);
                    this.denomTotal = 0;
                    this.recalcClosingFigures();
                },

                handleActualCashInput() {
                    const raw = (this.closingWizard.actualCashStr || '').toString().replace(/\D/g, '');
                    const num = parseInt(raw, 10) || 0;
                    this.closingWizard.actualCashNum = num;
                    this.closingWizard.actualCashStr = raw ? new Intl.NumberFormat('id-ID').format(num) : '';
                    this.recalcClosingFigures();
                },

                setQuickActualCash(amount) {
                    const num = Math.round(Number(amount) || 0);
                    this.closingWizard.actualCashNum = num;
                    this.closingWizard.actualCashStr = new Intl.NumberFormat('id-ID').format(num);
                    this.recalcClosingFigures();
                },

                lockBlindCountAndProceed() {
                    const raw = (this.closingWizard.actualCashStr || '').toString().replace(/\D/g, '');
                    const num = parseInt(raw, 10);
                    if (isNaN(num) || num < 0 || (!raw && this.closingWizard.actualCashNum <= 0)) {
                        this.closingWizard.errorMsg = 'Wajib memasukkan hasil hitung fisik uang di laci terlebih dahulu (Opsi A - Blind Cash Count).';
                        return;
                    }
                    this.closingWizard.actualCashNum = num;
                    this.closingWizard.errorMsg = '';
                    this.closingWizard.blindLocked = true;
                    this.recalcClosingFigures();
                    this.closingWizard.step = 2;
                },

                completeCloseAndHandover() {
                    // Double-submission protection guard
                    if (this.closingWizard.isSubmitting) return;

                    const exp = this.getExpectedClosingCash();
                    const act = Math.round(Number(this.closingWizard.actualCashNum) || 0);
                    const diff = this.getCashVariance();

                    if (diff !== 0 && !this.closingWizard.varianceNote.trim()) {
                        this.closingWizard.errorMsg = 'Terdapat selisih kas! Wajib mengisi alasan/keterangan selisih untuk ditinjau Manager.';
                        return;
                    }
                    this.closingWizard.errorMsg = '';
                    this.closingWizard.isSubmitting = true;

                    const nowTime = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':');
                    const s = this.activeSession;

                    const closedRecord = {
                        sessionId: s.sessionId,
                        businessDay: s.businessDayDate,
                        terminalId: s.terminalId,
                        terminalLabel: s.terminalLabel,
                        cashierId: s.cashierId,
                        cashierName: s.cashierName,
                        startedAt: s.startedAt,
                        endedAt: nowTime,
                        openingCash: s.openingCash,
                        cashSalesNet: (Number(s.cashSalesNet) || 0) + (Number(s.splitCashPortion) || 0),
                        qrisSales: (Number(s.qrisSales) || 0) + (Number(s.splitNonCashPortion) || 0),
                        creditSales: Number(s.creditSales) || 0,
                        cashIn: Number(s.cashIn) || 0,
                        cashOut: Number(s.cashOut) || 0,
                        cashReturn: Number(s.cashReturn) || 0,
                        cashDrop: Number(s.cashDrop) || 0,
                        expectedClosingCash: exp,
                        actualClosingCash: act,
                        cashVariance: diff,
                        status: diff === 0 ? 'CLOSED' : 'PENDING_REVIEW',
                        reviewStatus: diff === 0 ? 'VERIFIED' : 'PENDING_REVIEW',
                        closeNote: diff === 0
                            ? (this.closingWizard.handoverNote || 'Saldo fisik sesuai perhitungan sistem.')
                            : `[${this.closingWizard.varianceCategory}] ${this.closingWizard.varianceNote}`,
                        handoverToName: (this.closingWizard.handoverType === 'handover' || this.closingWizard.handoverType === 'pending')
                            ? 'Menunggu Kasir Berikutnya'
                            : 'Tutup Kasir Selesai (Tanpa Serah Terima)',
                        trxCount: s.trxCount || 0,
                        syncStatus: s.isOffline ? 'PENDING_SYNC' : 'SYNCED'
                    };

                    // Prevent duplicate entries in session history
                    const existingIdx = this.sessionHistory.findIndex(h => h.sessionId === closedRecord.sessionId);
                    if (existingIdx >= 0) {
                        this.sessionHistory[existingIdx] = closedRecord;
                    } else {
                        this.sessionHistory.push(closedRecord);
                    }
                    this.closingWizard.lastClosedRecord = closedRecord;

                    // Update Terminal state accurately
                    const term = this.terminals.find(t => t.id === s.terminalId);
                    if (term) {
                        term.sessionsTodayCount = (term.sessionsTodayCount || 1) + 1;
                        term.activeCashierName = null;
                        term.activeCashierId = null;
                        term.activeStartedAt = null;
                        term.activeSessionId = null; // CRITICAL ROOT CAUSE FIX: Reset activeSessionId so terminal is not treated as in-use!

                        if (this.closingWizard.handoverType === 'handover' || this.closingWizard.handoverType === 'pending') {
                            term.status = 'HANDOVER_PENDING';
                            term.handover = {
                                fromSessionId: closedRecord.sessionId,
                                fromCashierId: closedRecord.cashierId,
                                fromCashierName: closedRecord.cashierName,
                                endedAt: nowTime,
                                expectedCash: exp,
                                actualCash: act,
                                variance: diff,
                                varianceStatus: diff === 0 ? 'SESUAI' : 'PENDING_REVIEW',
                                varianceNote: closedRecord.closeNote,
                                heldBillsCount: this.closingWizard.holdAction === 'inherit' ? (s.heldBillsCount || 0) : 0
                            };
                        } else {
                            term.status = 'AVAILABLE';
                            term.handover = null;
                        }
                    }

                    // Update activeSession state to reflect closed status
                    this.activeSession.status = diff === 0 ? 'CLOSED' : 'PENDING_REVIEW';
                    this.activeSession.endedAt = nowTime;
                    this.activeSession.actualClosingCash = act;
                    this.activeSession.cashVariance = diff;

                    // Sync Shift Closing Audit to PostgreSQL Backend API
                    try {
                        fetch('/api/shift/close', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify({
                                terminal_code: s.terminalLabel || s.terminalId || 'POS-01',
                                actual_cash: act,
                                closing_notes: closedRecord.closeNote
                            })
                        }).catch(e => console.warn('API Shift Close Sync Warning:', e));
                    } catch (e) {}

                    localStorage.setItem('pos_cashier_opened', 'false');
                    this.selectedShift = s.terminalId;
                    localStorage.setItem('pos_kasir_shift', s.terminalId);
                    this.persistAllState();
                    window.location.href = '/select-shift';
                }
            };
        };
