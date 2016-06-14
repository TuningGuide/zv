'use strict';

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = undefined;

var _classCallCheck2 = require('babel-runtime/helpers/classCallCheck');

var _classCallCheck3 = _interopRequireDefault(_classCallCheck2);

var _possibleConstructorReturn2 = require('babel-runtime/helpers/possibleConstructorReturn');

var _possibleConstructorReturn3 = _interopRequireDefault(_possibleConstructorReturn2);

var _inherits2 = require('babel-runtime/helpers/inherits');

var _inherits3 = _interopRequireDefault(_inherits2);

var _QueryBuilderContextBase = require('./QueryBuilderContextBase');

var _QueryBuilderContextBase2 = _interopRequireDefault(_QueryBuilderContextBase);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var QueryBuilderContext = function (_QueryBuilderContextB) {
  (0, _inherits3.default)(QueryBuilderContext, _QueryBuilderContextB);

  function QueryBuilderContext() {
    (0, _classCallCheck3.default)(this, QueryBuilderContext);

    var _this = (0, _possibleConstructorReturn3.default)(this, _QueryBuilderContextB.call(this));

    _this.runBefore = [];
    _this.runAfter = [];
    _this.onBuild = [];
    return _this;
  }

  QueryBuilderContext.prototype.clone = function clone() {
    var ctx = _QueryBuilderContextB.prototype.clone.call(this);

    ctx.runBefore = this.runBefore.slice();
    ctx.runAfter = this.runAfter.slice();
    ctx.onBuild = this.onBuild.slice();

    return ctx;
  };

  return QueryBuilderContext;
}(_QueryBuilderContextBase2.default);

exports.default = QueryBuilderContext;