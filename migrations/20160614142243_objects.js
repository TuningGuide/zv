
exports.up = function(knex, Promise) {
	return knex.schema
		.createTable('Object', function (table) {
			table.bigincrements('id').primary();
			table.string('address');
			table.string('kind');
			table.string('marketValue');
			table.string('description');
			table.string('debtee');
			table.string('qm');
			table.string('rent');
			table.string('parkingSite');
			table.string('balcony');
			table.string('lift');
			table.string('level');
			table.string('sinkingFund');
		});
};

exports.down = function(knex, Promise) {
	return knex.schema
		.dropTableIfExists('Object');
};
