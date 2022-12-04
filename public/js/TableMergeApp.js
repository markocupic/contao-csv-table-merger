class TableMergeApp {
    constructor(vueElement, opt) {

        const defaults = {
            'delimiters': ['[[', ']]'],
            'session_key': null,
            'routes': {
                'initialize': null,
                'merge': null,
            }
        }

        const options = {...defaults, ...opt};

        //const {createApp} = Vue

        const app = Vue.createApp({
            //delimiters: ['[[ ', ' ]]'],
            //delimiters: ['${', '}'],

            data() {
                return {
                    messages: '<p class="tl_info">Initializaton started. Please wait...</p>',
                    initialization_succeed: false,
                    record_count: -1,
                    requests_required: -1,
                    requests_remained: -1,
                    requests_completed: -1,
                    import_process_stopped_with_error: false,
                }
            },

            // Lifecycle hooks are called at different stages
            // of a component's lifecycle.
            // This function will be called when the component is mounted.
            async mounted() {
                await this.initialize();
                if (this.initialization_succeed && !this.import_process_stopped_with_error) {
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
                                this.messages = this.messages + data.messages;
                                this.record_count = data.record_count;
                                this.requests_required = data.requests_required;
                                this.requests_remained = data.requests_remained;
                                this.requests_completed = data.requests_completed;

                            } else {
                                this.initialization_succeed = false;
                                this.import_process_stopped_with_error = true;
                            }
                        });
                },

                async run() {
                    return fetch(options.routes.merge)
                        .then((response) => response.json())
                        .then((data) => {
                            if (data.success === true) {
                                this.messages = this.messages + data.messages;
                                this.requests_remained = data.requests_remained;
                                if(parseInt(data.requests_remained) > 0)
                                {
                                    window.setTimeout(() => this.run(), 2000);
                                }else{
                                    console.log('fertig');
                                }
                            } else {
                                this.import_process_stopped_with_error = true;
                            }
                        });
                }
            },
        });
        app.config.compilerOptions.delimiters = ['[[', ']]'];
        const mountedApp = app.mount(vueElement);

        return mountedApp;

    }
}
