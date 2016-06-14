'use strict';

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = deprecated;

var _lodash = require('lodash');

var _lodash2 = _interopRequireDefault(_lodash);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

function deprecated(opt) {
  return function (target, property, descriptor) {
    var message = property + ' is deprecated and will be removed in version ' + opt.removedIn + '. Use ' + opt.useInstead + ' instead.';

    var value = descriptor.value;
    var getter = descriptor.get;

    if (_lodash2.default.isFunction(value)) {
      descriptor.value = function () {
        console.warn(message);
        return value.apply(this, arguments);
      };
    }

    if (_lodash2.default.isFunction(getter)) {
      descriptor.get = function () {
        console.warn(message);
        return getter.apply(this, arguments);
      };
    }
  };
}