/**
 * Created by velten on 14.06.16.
 */
var Model = require('objection').Model;

/**
 * @extends Model
 * @constructor
 */
function Object() {
	Model.apply(this, arguments);
}

Model.extend(Object);
module.exports = Object;

Object.tableName = 'Object';