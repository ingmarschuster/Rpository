package com.github.rpository;

import java.sql.ResultSet;
import java.sql.SQLException;

import org.springframework.jdbc.core.RowCallbackHandler;

public class AuthorsCallbackHandler implements RowCallbackHandler {
	public static final String authorsStmt = "SELECT authors.author_id, authors.primary_contact, authors.seq,"
			+ " authors.first_name, authors.middle_name, authors.last_name, authors.country, authors.email"
			+ " FROM published_articles" + " JOIN authors ON published_articles.article_id = authors.submission_id"
			+ " WHERE published_articles.article_id = ?" + " ORDER BY authors.seq;";
	private final StringBuilder pkgName;
	private final StringBuilder authors;
	private final PackageDescription pd;

	public AuthorsCallbackHandler(final StringBuilder authors, final StringBuilder pkgName, final PackageDescription pd) {
		this.authors = authors;
		this.pkgName = pkgName;
		this.pd = pd;
	}

	public void processRow(ResultSet rs) throws SQLException {
		if (authors != null) {
			if (authors.toString().startsWith("c(person(")) {
				authors.append(", ");
			}
			authors.append("person(");
			authors.append("given = \"").append(rs.getString(4)).append("\", family = \"").append(rs.getString(6))
					.append("\"");
			if (rs.getString(5) != null && rs.getString(5).length() > 0) {
				authors.append(", middle = \"").append(rs.getString(5)).append("\"");
			}
			if (rs.getString(8) != null && rs.getString(8).length() > 0) {
				authors.append(", email = \"").append(rs.getString(8)).append("\"");
			}

			authors.append(", role = c(\"aut\"");
			if (rs.getInt(2) == 1) {
				// primary_contact
				authors.append(", \"cre\"");
			}
			authors.append("))");
		}
		if (pkgName != null) {
			pkgName.append(rs.getString(6));
		}
		if (pd != null) {
			if (pd.get("Author").length() != 0) {
				pd.set("Author", pd.get("Author") + " and ");
			}
			if (rs.getInt(2) == 1) {
				pd.set("Maintainer", rs.getString(4) + " " + rs.getString(6) + " <" + rs.getString(8) + ">");
			}
			pd.set("Author", pd.get("Author") + rs.getString(4) + " " + rs.getString(6));
		}
	}
}
