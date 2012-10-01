package com.github.rpository;

import java.sql.ResultSet;
import java.sql.SQLException;

import org.springframework.jdbc.core.RowCallbackHandler;

public class ArticleCallbackHandler implements RowCallbackHandler {
	public static final String articleStmt = "SELECT published_articles.issue_id, published_articles.date_published, "
			+ "S1.setting_value, S2.setting_value" + " FROM published_articles "
			+ "JOIN article_settings S1 ON published_articles.article_id = S1.article_id "
			+ "JOIN article_settings S2 ON published_articles.article_id = S2.article_id "
			+ "WHERE S1.setting_name = \"title\" AND S2.setting_name = \"abstract\" "
			+ "AND published_articles.article_id = ? LIMIT 1";

	private final PackageDescription pd;
	private final StringBuilder pkgName;

	public ArticleCallbackHandler(final PackageDescription pd, final StringBuilder pkgName) {
		this.pd = pd;
		this.pkgName = pkgName;
	}

	public void processRow(ResultSet rs) throws SQLException {
		final String d = rs.getString(2).split(" ", 2)[0];
		if (pd != null) {
			pd.set("Date", d);
			pd.set("Title", rs.getString(3));
			pd.set("Description", rs.getString(4));
		}
		if (pkgName != null) {
			pkgName.append(d.split("-", 2)[0]);
		}
	}

}
