/**
 * RecaptchaV3 Handler
 */
const RecaptchaV3Handler = function() {};
RecaptchaV3Handler.prototype = {
  field: null,
  configuration: { action: 'submit' },
  siteKey: '',
  threshold: 30000,
  // Initiate
  init: function(options) {
    if(!options.siteKey) {
      throw new 'no site key present';
    }
    this.siteKey = options.siteKey;
    if(options.configuration) {
      this.configuration = options.configuration;
    }
    if(options.threshold > 0) {
      this.threshold = options.threshold;
    }
    this.field = null;
    if(options.id) {
      this.field = document.getElementById(options.id);
    }
    if(!this.field) {
        throw 'field ' + options.id + ' does not exist';
    }
    return this;
  },
  // determine if the token needs a refresh
  requireRefresh: function() {
    if(this.field.dataset.isPending == '1') {
      return false;
    }
    if(this.field.value == '') {
      return true;
    }
    let iv = 0;
    let dlc = this.field.dataset.lastcheck;
    if(dlc) {
      iv = Date.now() - dlc;
    }
    return iv > this.threshold;
  },
  // Attempt to retrieve a token
  execute: function(evt) {
    if(evt) {
      evt.stopPropagation();
    }
    if(!this.requireRefresh()) {
      return null;
    }
    if(this.field.dataset.isPending == '1') {
      return null;
    }
    this.field.dataset.isPending = '1';
    let _self = this;
    grecaptcha.execute(
        this.siteKey,
        this.configuration
    ).then(
        function(token) {
          _self.field.value = token;
          _self.field.dataset.lastcheck = Date.now();
        },
        function(r) {
          console.warn('rejected');
        }
    ).catch(
        function(fail) {
          console.warn('catch in promise:' + fail);
        }
    ).then(
      function() {
        delete _self.field.dataset.isPending;
      }
    );
    return true;
  },
  // Bind events on form fields that would retrieve a token
  bindEvents: function() {
    let _self = this;
    Array.from(this.field.form.elements).filter(
      (input) => {
        return input.type != 'submit' && input.type != 'fieldset';
      }
    ).forEach(
      (input) => {
        input.addEventListener(
            'focus',
            function(evt) {
                _self.execute(evt);
            }
        );
        input.addEventListener(
            'change',
            function(evt) {
                _self.execute(evt);
            }
        );
      }
    );
    return this;
  }
};
