class TableMergeApp {
    constructor(vueElement, opt) {

        const defaults = {
            'session_key': null,
            'routes': {
                'initialize': null,
                'merge': null,
            }
        }

        const options = {...defaults, ...opt};

        const {createApp} = Vue

        const app = createApp({

            data() {
                return {
                    messages: [],
                    perc_loaded: 0,
                    initialization_succeed: false,
                    count_records: -1,
                    requests_required: -1,
                    requests_pending: -1,
                    requests_completed: -1,
                    merging_process_stopped_with_error: false,
                    merging_process_completed: false,
                    count_inserts: 0,
                    count_updates: 0,
                    count_deletions: 0,
                }
            },
            watch: {
                // Auto scroll to bottom, if there are new messages
                messages(newMessages, oldMessages) {
                    const box = document.getElementById("cctmSummaryBox");
                    window.setTimeout(() => box.scrollTop = box.scrollHeight + 100, 100);
                }
            },

            // Lifecycle hooks are called at different stages
            // of a component's lifecycle.
            // This function will be called when the component is mounted.
            async mounted() {
                await this.initialize();
                if (this.initialization_succeed && !this.merging_process_stopped_with_error) {
                    await this.run();
                }
            },

            methods: {
                async initialize() {
                    return fetch(options.routes.initialize)
                        .then((response) => response.json())
                        .then((data) => {
                            if (data.success === true) {
                                this.initialization_succeed = true;
                                this.messages = [...this.messages, ...data.messages];
                                this.count_records = data.count_records;
                                this.requests_required = data.requests_required;
                                this.requests_pending = data.requests_pending;
                                this.requests_completed = data.requests_completed;
                            } else {
                                if (typeof data.messages !== 'undefined' && data.messages) {
                                    this.messages = [...this.messages, ...data.messages];
                                }
                                this.initialization_succeed = false;
                                this.merging_process_stopped_with_error = true;
                            }
                            this.updateProgressBar();
                        }).catch(function (error) {
                            this.merging_process_stopped_with_error = true;
                        });
                },

                async run() {
                    return fetch(options.routes.merge)
                        .then((response) => response.json())
                        .then((data) => {
                            if (data.success === true) {
                                this.messages = [...this.messages, ...data.messages];
                                this.count_inserts = data.count_inserts;
                                this.count_updates = data.count_updates;
                                this.count_deletions = data.count_deletions;
                                this.requests_pending = data.requests_pending;
                                this.requests_completed = data.requests_completed;

                                if (parseInt(data.requests_pending) > 0) {
                                    this.updateProgressBar();
                                    this.run();
                                } else {
                                    this.merging_process_completed = true;
                                    this.updateProgressBar();
                                }
                            } else {
                                if (data) {
                                    if (typeof data.messages !== 'undefined' && data.messages) {
                                        this.messages = [...this.messages, ...data.messages];
                                    }

                                    if (typeof data.count_inserts !== 'undefined' && data.count_inserts) {
                                        this.count_inserts = data.count_inserts;
                                    }

                                    if (typeof data.count_updates !== 'undefined' && data.count_updates) {
                                        this.count_updates = data.count_updates;
                                    }

                                    if (typeof data.count_deletions !== 'undefined' && data.count_deletions) {
                                        this.count_deletions = data.count_deletions;
                                    }
                                }
                                this.updateProgressBar();
                                this.merging_process_stopped_with_error = true;
                            }
                        }).catch(function (error) {
                            this.merging_process_stopped_with_error = true;
                        });
                },

                updateProgressBar: function updateProgressBar() {
                    const bar = document.getElementById('cctmImportProgress');
                    const percentage = document.querySelector('#cctmImportProgress .cctm-percentage');
                    if (bar && this.requests_required > 0) {
                        this.perc_loaded = Math.ceil(this.requests_completed / this.requests_required * 100);
                        if (this.requests_pending === 0) {
                            this.perc_loaded = 100;
                        }
                        bar.style.width = this.perc_loaded + '%';
                        if (percentage && this.perc_loaded > 8) {
                            percentage.innerHTML = this.perc_loaded + '%';
                        }
                    }
                }
            },
        });
        app.config.compilerOptions.delimiters = ['[[', ']]'];
        const mountedApp = app.mount(vueElement);

        return mountedApp;

    }
}
