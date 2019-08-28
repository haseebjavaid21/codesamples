({
    /**
     * CRED-1068 : Manageability of pop-up
     */
    parmasJSON: [],
    parmasData: [],
    freshData: 'new',
    events: {
        'click #save_params': 'performValidation',
    },
    initialize: function (options) {
        this._super('initialize', [options]);
        this.popup_status = this.meta.panels[0].fields[0];
        this.popup_visibility_start = this.meta.panels[0].fields[1];
        this.popup_visibility_end = this.meta.panels[0].fields[2];
        this.popup_one_duration = this.meta.panels[0].fields[3];
        /**
         * CRED-1200: On-Screen-Alert for New-Lead-Notification 
         * After first reminder pop-up will be shown each minutes. So no need if second pop-up duration
         */
        // this.popup_two_duration = this.meta.panels[0].fields[4];
        this.popup_days = this.meta.panels[0].fields[4];
    },
    _renderHtml: function () {
        this._super('_renderHtml');
    },
    /**
     * Retrieve Data for Config and Populate to model
     * @returns NULL
     */
    loadData: function () {
        var url = App.api.buildURL("retrievePopUpConfig", null, null);
        App.api.call('read', url, null, {
            success: _.bind(function (response) {
                this.parmasData = JSON.parse(response.configuration);
                _.each(this.meta.panels[0].fields, _.bind(function (field) {
                    this.model.set(field.name, this.parmasData[field.name]);
                }, this));

                this.freshData = 'old';
            }, this),
            error: function () {

            }
        });
    },
    /**
     * Update Config Data in DB
     * @returns NULL
     */
    saveParams: function () {
        var configs = {};
        this.configJSON = [];

        configs = {'popup_status': this.model.get('popup_status') ? 1 : 0, 'popup_visibility_start': this.model.get('popup_visibility_start'),
            'popup_visibility_end': this.model.get('popup_visibility_end'), 'popup_one_duration': this.model.get('popup_one_duration'),
            'popup_days': this.model.get('popup_days')};
        this.parmasJSON = configs;

        var configData = JSON.stringify(this.parmasJSON);
        var url = App.api.buildURL("savePopUpConfig", null, null);

        app.api.call('create', url, {'configuration': configData, 'type': this.freshData}, {
            success: _.bind(function (response) {
                App.alert.dismiss('saving-message');
                app.alert.show("success", {
                    level: 'success',
                    messages: app.lang.get('LBL_DATA_SAVED_SUCCESS', 'Leads'),
                    autoClose: true
                });
                this.configJSON = [];

                SUGAR.App.router.navigate("Administration", {trigger: true});
            }, this),
            error: function () {
                app.alert.show("success", {
                    level: 'error',
                    messages: app.lang.get('LBL_DATA_SAVED_FAIL', 'Leads'),
                    autoClose: true
                });
                this.configJSON = [];
            }
        });

    },
    /**
     * Check for empty fields before saving
     * @returns NULL
     */
    performValidation: function () {
        var custom_message = null;
        var validationContainer = [];

        _.each(this.meta.panels[0].fields, _.bind(function (field, index) {
            if (field.name != 'popup_status') {
                if (_.isEmpty(this.$('input[name="' + field.name + '"]').val()) ||
                    _.isUndefined(this.$('input[name="' + field.name + '"]').val())) {
                    validationContainer.push(false);
                }
                /**
                 * CRED-1406 : On-Screen-Alert Enhancements
                 */
                if (_.isEqual(field.name, 'popup_one_duration') &&
                    isNaN((this.$('input[name="' + field.name + '"]').val()))) {
                    validationContainer.push(false);
                    custom_message = "'" + this.$('input[name="' + field.name + '"]').val()
                            + "' is not a number";
                }
            }
        }, this));

        if (_.contains(validationContainer, false)) {
            this.showError(custom_message);
        } else {
            this.saveParams();
        }
    },
    /**
     * Display error message after validation
     * @param string custom_message
     * @returns undefined
     */
    showError: function (custom_message) {
        app.alert.dismiss('missing-data');
        app.alert.show("missing-data", {
            level: 'error',
            messages: custom_message || app.lang.get('LBL_FIELDS_CHECK', 'Leads'),
            autoClose: true
        });
    }
})
