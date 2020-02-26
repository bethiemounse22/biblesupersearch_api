enyo.kind({
    name: 'AICWEBTECH.Enyo.UniqueText',
    // kind: 'enyo.Input',
    valueCached: null,
    value: null,
    selectOnFocus: true,
    apiUrl: null,
    fieldName: null,
    ignorePk: null,
    classes: 'unique_text',

    handlers: {
        // onchange: 'handleChange',
        // onfocus: 'handleFocus'
        onViewForm: 'handleView'
    },

    components: [ {name: 'Input', kind: 'enyo.Input', onfocus: 'handleFocus', onchange: 'handleChange'} ],

    bindings: [
        {from: 'value', to: '$.Input.value', oneWay: false, transform: function(value, dir) {
            this.log('UniqueText', value, dir);

            if(dir == 1) {
                this.set('valueCached', value);
            }

            return value || '';
        }},
    ],

    create: function() {
        this.inherited(arguments);
        this.log();       
        // this.render(); 
    },

    /*
    render: function() {
        this.inherited(arguments);
        this.log();
        this._rendered();
    },

    _rendered: function() {
        this.inherited(arguments);
        this.log();

        if(this.hasNode()) {
            this.log('creating focus');

            $(this.hasNode()).focus(function() {
                console.log('focused');
            });
        }
        else {
            this.log('NO DOM NODE!');
        }
    },

    rendered: enyo.inherit(function (sup) {
        return function() {
            sup.apply(this, arguments);

            this.log('rendered');

            alert('rendered');
        };
    }),
    */

    handleView: function(inSender, inEvent) {
        this.log(inEvent);
        this.set('ignorePk', inEvent.pk);
    },

    handleChange: function(inSender, inEvent) {
        this.log();

        var fieldName = this.get('fieldName') || this.get('name');

        // this.log(fieldName);

        var postData = {
            field_name: fieldName,
            value: this.get('value'),
            id: this.get('ignorePk'),
            _token: laravelCsrfToken
        };

        this.log(postData);

        var ajax = new enyo.Ajax({
            url: this.apiUrl,
            method: 'POST',
            postBody: postData,
            headers: this.app.defaultAjaxHeaders,
            cacheBust: false
        });

        ajax.response(this, function(inSender, inResponse) {
            this.app.set('ajaxLoading', false);

            if(!inResponse.success) {
                this.set('value', this.get('valueCached'));
                return this.app._errorHandler(inSender, inResponse)
            }

            this.app.refreshGrid();
            this.set('valueCached', this.get('value'));
        });

        ajax.error(this, function(inSender, inResponse) {
            console.log('ERROR', inSender, inResponse);
            this.app.set('ajaxLoading', false);
            this.set('value', this.get('valueCached'));
            var response = JSON.parse(inSender.xhrResponse.body);
            this.app._errorHandler(inSender, response);
        });

        ajax.go();
    }, 

    valueChanged: function(was, is) {
        // this.inherited(arguments);
        this.log(was, is);
    }
});

