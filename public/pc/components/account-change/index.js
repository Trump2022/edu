define([
  'store/index',
  'api/login',
  'api/my',
  'api/auth',
  'plugins/blueimp-md5/js/md5',
  'text!./index.html',
  'css!./index.css'
], function (store, loginApi, myApi, authApi, md5, html) {
  return {
    inject: ['logout'],
    props: {
      currentPhone: {
        type: String,
        default: ''
      }
    },
    data: function () {
      return {
        state: true,
        phone: '',
        code: '',
        pwd: '',
        count: -1,
        TIME_COUNT: 60,
        storeState: store.state
      };
    },
    watch: {
      'storeState.accountVisible': function (value) {
        this.$nextTick(function () {
          var vm = this;
          if (value) {
            $('#captcha').slideVerify({
              baseUrl: '/web/auth_api',
              mode: 'pop',
              containerId: 'getCode',
              beforeCheck: function () {
                var flag = true;
                return flag
              },
              ready: function () { },
              success: function (params) {
                vm.getCode(params.captchaVerification);
              },
              error: function () { },
              beforeShow: function () {
                var flag = true;
                return flag;
              }
            });
          }
        });
      },
      count: function (value) {
        if (value < 0) {
          this.clearTimer();
        }
      }
    },
    methods: {
      clearTimer: function () {
        clearInterval(this.timer);
        this.timer = null;
      },
      // 获取验证码
      getCode: function (captchaVerification) {
        var vm = this;
        if (this.storeState.isAccount && !this.state) {
          if (!this.phone) {
            return this.$message.warning('请输入新手机号');
          }
          if (!/^1[3456789]\d{9}$/.test(this.phone)) {
            return this.$message.warning('新手机号错误');
          }
        }
        this.count = this.TIME_COUNT;
        this.timer = setInterval(function () {
          vm.count--;
        }, 1000);
        loginApi.code({
          phone: this.storeState.isAccount && !this.state ? this.phone : this.currentPhone,
          captchaVerification: captchaVerification,
          captchaType: 'blockPuzzle'
        }).then(function (res) {
          vm.$message.success(res.msg);
        }).catch(function (err) {
          vm.$message.error(err.msg);
          vm.count = -1;
        });
      },
      submit: function () {
        var vm = this;
        if (this.storeState.isAccount && !this.state) {
          if (!this.phone) {
            return this.$message.warning('请输入新手机号');
          }
          if (!/^1[3456789]\d{9}$/.test(this.phone)) {
            return this.$message.warning('新手机号错误');
          }
        }
        if (!this.code) {
          return this.$message.warning('请输入验证码');
        }
        if (!/^\d{6}$/.test(this.code)) {
          return this.$message.warning('验证码错误');
        }
        if (!this.storeState.isAccount) {
          if (!this.pwd) {
            return this.$message.warning('请输入新密码');
          }
          if (!/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{8,16}$/.test(this.pwd)) {
            return this.$message.warning('请输入8-16位字母加数字组合新密码');
          }
        }
        if (this.storeState.isAccount) {
          if (this.state) {
            // 验证旧手机号
            myApi.validate_code({
              phone: this.currentPhone,
              code: this.code
            }).then(function () {
              vm.state = false;
              vm.code = '';
              vm.count = -1;
            }).catch(function (err) {
              vm.$message.error(err.msg);
              vm.count = -1;
            });
          } else {
            // 保存新手机号
            myApi.save_phone({
              phone: this.phone,
              code: this.code
            }).then(function (res) {
              vm.$message.success(res.msg);
              vm.accountClose();
              vm.logout();
            }).catch(function (err) {
              vm.$message.error(err.msg);
              vm.count = -1;
            });
          }
        } else {
          // 修改密码
          loginApi.register({
            account: this.currentPhone,
            code: this.code,
            pwd: md5(this.pwd),
            type: 2
          }).then(function (res) {
            vm.$message.success(res.msg);
            vm.accountClose();
            vm.logout();
          }).catch(function (err) {
            vm.$message.error(err.msg);
            vm.count = -1;
          });
        }
      },
      accountClose: function () {
        this.phone = '';
        this.code = '';
        this.pwd = '';
        this.count = -1;
        store.setAccountAction(false);
      }
    },
    template: html
  };
});